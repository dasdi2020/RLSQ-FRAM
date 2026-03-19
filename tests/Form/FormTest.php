<?php

declare(strict_types=1);

namespace Tests\Form;

use PHPUnit\Framework\TestCase;
use RLSQ\Form\Form;
use RLSQ\Form\FormBuilder;
use RLSQ\Form\FormFactory;
use RLSQ\Form\Type\AbstractType;
use RLSQ\Form\Validation\Constraint\Email;
use RLSQ\Form\Validation\Constraint\Length;
use RLSQ\Form\Validation\Constraint\NotBlank;
use RLSQ\HttpFoundation\Request;

class FormTest extends TestCase
{
    private FormFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FormFactory();
    }

    // --- Soumission et validation ---

    public function testHandleRequestBindsData(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $request = Request::create('/contact', 'POST', [
            'contact' => ['name' => 'Alice', 'email' => 'alice@test.com', 'message' => 'Bonjour, ceci est un message.'],
        ]);

        $form->handleRequest($request);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());

        $data = $form->getData();
        $this->assertSame('Alice', $data['name']);
        $this->assertSame('alice@test.com', $data['email']);
    }

    public function testValidationErrors(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $request = Request::create('/contact', 'POST', [
            'contact' => ['name' => '', 'email' => 'invalid', 'message' => ''],
        ]);

        $form->handleRequest($request);

        $this->assertTrue($form->isSubmitted());
        $this->assertFalse($form->isValid());

        $errors = $form->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('message', $errors);
    }

    public function testNotSubmittedOnGet(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $request = Request::create('/contact', 'GET');
        $form->handleRequest($request);

        $this->assertFalse($form->isSubmitted());
    }

    // --- Binding vers un objet ---

    public function testBindToObject(): void
    {
        $article = new ArticleDTO();

        $builder = $this->factory->createBuilder('article');
        $builder->add('title', 'text', ['constraints' => [new NotBlank()]]);
        $builder->add('content', 'textarea');

        $form = $this->factory->createFromBuilder($builder, $article);

        $request = Request::create('/article', 'POST', [
            'article' => ['title' => 'Mon article', 'content' => 'Le contenu'],
        ]);

        $form->handleRequest($request);

        $this->assertTrue($form->isValid());
        $this->assertSame('Mon article', $article->title);
        $this->assertSame('Le contenu', $article->content);
    }

    public function testBindToObjectCastsInt(): void
    {
        $dto = new ProductDTO();

        $builder = $this->factory->createBuilder('product');
        $builder->add('name', 'text');
        $builder->add('price', 'number');

        $form = $this->factory->createFromBuilder($builder, $dto);

        $request = Request::create('/product', 'POST', [
            'product' => ['name' => 'Widget', 'price' => '19'],
        ]);

        $form->handleRequest($request);

        $this->assertSame('Widget', $dto->name);
        $this->assertSame(19, $dto->price);
    }

    // --- FormView / Rendu HTML ---

    public function testCreateView(): void
    {
        $form = $this->factory->create(ContactFormType::class);
        $view = $form->createView();

        $this->assertSame('contact', $view->name);
        $this->assertSame('POST', $view->method);
        $this->assertCount(4, $view->children); // name, email, message, submit

        $this->assertArrayHasKey('name', $view->children);
        $this->assertSame('text', $view->children['name']->type);
        $this->assertSame('contact[name]', $view->children['name']->fullName);
    }

    public function testRenderHtml(): void
    {
        $form = $this->factory->create(ContactFormType::class);
        $html = $form->createView()->render();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="contact"', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('type="text"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('<textarea', $html);
        $this->assertStringContainsString('<button type="submit"', $html);
        $this->assertStringContainsString('</form>', $html);
    }

    public function testRenderWithErrors(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $request = Request::create('/contact', 'POST', [
            'contact' => ['name' => '', 'email' => '', 'message' => ''],
        ]);
        $form->handleRequest($request);

        $html = $form->createView()->render();

        $this->assertStringContainsString('form-error', $html);
        $this->assertStringContainsString('vide', $html);
    }

    public function testRenderWithValues(): void
    {
        $form = $this->factory->create(ContactFormType::class, [
            'name' => 'Alice',
            'email' => 'alice@test.com',
        ]);

        $html = $form->createView()->render();

        $this->assertStringContainsString('value="Alice"', $html);
        $this->assertStringContainsString('value="alice@test.com"', $html);
    }

    public function testChoiceFieldRendersSelect(): void
    {
        $builder = $this->factory->createBuilder('survey');
        $builder->add('color', 'select', [
            'choices' => ['Rouge' => 'red', 'Bleu' => 'blue', 'Vert' => 'green'],
        ]);

        $form = $this->factory->createFromBuilder($builder, ['color' => 'blue']);
        $html = $form->createView()->render();

        $this->assertStringContainsString('<select', $html);
        $this->assertStringContainsString('value="blue" selected', $html);
        $this->assertStringContainsString('Rouge', $html);
    }

    // --- FormFactory ---

    public function testCreateFromTypeClass(): void
    {
        $form = $this->factory->create(ContactFormType::class);

        $this->assertSame('contact', $form->getName());
    }

    public function testCreateWithOptions(): void
    {
        $form = $this->factory->create(ContactFormType::class, null, [
            'action' => '/submit',
            'method' => 'PUT',
        ]);

        $view = $form->createView();
        $this->assertSame('/submit', $view->action);
        $this->assertSame('PUT', $view->method);
    }
}

// === Fixtures ===

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilder $builder, array $options): void
    {
        $builder
            ->add('name', 'text', [
                'label' => 'Nom',
                'required' => true,
                'constraints' => [new NotBlank(), new Length(min: 2, max: 100)],
            ])
            ->add('email', 'email', [
                'label' => 'Email',
                'constraints' => [new NotBlank(), new Email()],
            ])
            ->add('message', 'textarea', [
                'label' => 'Message',
                'constraints' => [new NotBlank(), new Length(min: 10)],
            ])
            ->add('submit', 'submit', [
                'label' => 'Envoyer',
            ]);
    }
}

class ArticleDTO
{
    public string $title = '';
    public string $content = '';
}

class ProductDTO
{
    public string $name = '';
    public int $price = 0;
}

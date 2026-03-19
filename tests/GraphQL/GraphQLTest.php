<?php

declare(strict_types=1);

namespace Tests\GraphQL;

use PHPUnit\Framework\TestCase;
use RLSQ\GraphQL\Executor;
use RLSQ\GraphQL\FieldDefinition;
use RLSQ\GraphQL\GraphiQL;
use RLSQ\GraphQL\Schema;
use RLSQ\GraphQL\TypeDefinition;

class GraphQLTest extends TestCase
{
    private function createSchema(): Schema
    {
        $schema = new Schema();

        // Types
        $schema->addType(
            (new TypeDefinition('Article', 'Un article'))
                ->addField('id', 'Int!')
                ->addField('title', 'String!')
                ->addField('body', 'String'),
        );

        $schema->addType(
            (new TypeDefinition('User'))
                ->addField('id', 'Int!')
                ->addField('name', 'String!'),
        );

        // Données de test
        $articles = [
            ['id' => 1, 'title' => 'Hello', 'body' => 'World', 'author_id' => 1],
            ['id' => 2, 'title' => 'GraphQL', 'body' => 'Rocks', 'author_id' => 1],
            ['id' => 3, 'title' => 'PHP', 'body' => '8.4', 'author_id' => 2],
        ];

        // Queries
        $articlesQuery = new FieldDefinition('[Article]', function ($ctx, $args) use ($articles) {
            $limit = $args['limit'] ?? 10;
            return array_slice($articles, 0, $limit);
        });
        $articlesQuery->addArg('limit', 'Int');
        $schema->addQuery('articles', $articlesQuery);

        $articleQuery = new FieldDefinition('Article', function ($ctx, $args) use ($articles) {
            foreach ($articles as $a) {
                if ($a['id'] === ($args['id'] ?? null)) {
                    return $a;
                }
            }
            return null;
        });
        $articleQuery->addArg('id', 'Int!');
        $schema->addQuery('article', $articleQuery);

        $schema->addQuery('hello', new FieldDefinition('String!', fn ($ctx, $args) => 'Hello ' . ($args['name'] ?? 'World')));

        // Mutations
        $createArticle = new FieldDefinition('Article', function ($ctx, $args) {
            return ['id' => 99, 'title' => $args['title'] ?? 'Untitled', 'body' => $args['body'] ?? ''];
        });
        $createArticle->addArg('title', 'String!');
        $createArticle->addArg('body', 'String');
        $schema->addMutation('createArticle', $createArticle);

        return $schema;
    }

    // --- Schema ---

    public function testSchemaSDL(): void
    {
        $schema = $this->createSchema();
        $sdl = $schema->toSDL();

        $this->assertStringContainsString('type Article', $sdl);
        $this->assertStringContainsString('id: Int!', $sdl);
        $this->assertStringContainsString('type Query', $sdl);
        $this->assertStringContainsString('articles(limit: Int): [Article]', $sdl);
        $this->assertStringContainsString('type Mutation', $sdl);
    }

    // --- Executor queries ---

    public function testSimpleQuery(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('{ hello }');

        $this->assertSame('Hello World', $result['data']['hello']);
    }

    public function testQueryWithArgs(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('{ article(id: 1) { id title } }');

        $this->assertSame(1, $result['data']['article']['id']);
        $this->assertSame('Hello', $result['data']['article']['title']);
        $this->assertArrayNotHasKey('body', $result['data']['article']); // Non sélectionné
    }

    public function testQueryList(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('{ articles { id title } }');

        $this->assertCount(3, $result['data']['articles']);
        $this->assertSame('Hello', $result['data']['articles'][0]['title']);
        $this->assertArrayNotHasKey('body', $result['data']['articles'][0]);
    }

    public function testQueryListWithLimit(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('{ articles(limit: 2) { title } }');

        $this->assertCount(2, $result['data']['articles']);
    }

    public function testMultipleFields(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('{ hello articles(limit: 1) { title } }');

        $this->assertSame('Hello World', $result['data']['hello']);
        $this->assertCount(1, $result['data']['articles']);
    }

    public function testQueryWithPrefix(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('query { hello }');

        $this->assertSame('Hello World', $result['data']['hello']);
    }

    public function testNamedQuery(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('query GetHello { hello }');

        $this->assertSame('Hello World', $result['data']['hello']);
    }

    // --- Mutations ---

    public function testMutation(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('mutation { createArticle(title: "New Post", body: "Content") { id title body } }');

        $this->assertSame(99, $result['data']['createArticle']['id']);
        $this->assertSame('New Post', $result['data']['createArticle']['title']);
        $this->assertSame('Content', $result['data']['createArticle']['body']);
    }

    // --- Errors ---

    public function testUnknownField(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('{ nonexistent }');

        $this->assertArrayHasKey('errors', $result);
    }

    public function testInvalidQuery(): void
    {
        $executor = new Executor($this->createSchema());
        $result = $executor->execute('not a query');

        $this->assertArrayHasKey('errors', $result);
    }

    // --- GraphiQL ---

    public function testGraphiQLRender(): void
    {
        $html = GraphiQL::render('/graphql', 'Test API');

        $this->assertStringContainsString('graphiql', $html);
        $this->assertStringContainsString('/graphql', $html);
        $this->assertStringContainsString('Test API', $html);
        $this->assertStringContainsString('GraphQL', $html);
    }
}

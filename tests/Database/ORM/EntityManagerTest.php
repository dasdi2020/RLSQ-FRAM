<?php

declare(strict_types=1);

namespace Tests\Database\ORM;

use PHPUnit\Framework\TestCase;
use RLSQ\Database\Connection;
use RLSQ\Database\ORM\EntityManager;
use RLSQ\Database\ORM\Mapping\Column;
use RLSQ\Database\ORM\Mapping\Entity;
use RLSQ\Database\ORM\Mapping\GeneratedValue;
use RLSQ\Database\ORM\Mapping\Id;

class EntityManagerTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $conn = new Connection('sqlite::memory:');
        $this->em = new EntityManager($conn);
        $this->em->createSchema([TestUser::class, TestPost::class]);
    }

    // --- Persist + Flush ---

    public function testPersistAndFlush(): void
    {
        $user = new TestUser();
        $user->name = 'Alice';
        $user->email = 'alice@test.com';

        $this->em->persist($user);
        $this->em->flush();

        $this->assertSame(1, $user->id);
    }

    public function testFind(): void
    {
        $user = new TestUser();
        $user->name = 'Bob';
        $user->email = 'bob@test.com';

        $this->em->persist($user);
        $this->em->flush();

        $found = $this->em->find(TestUser::class, $user->id);

        $this->assertNotNull($found);
        $this->assertSame('Bob', $found->name);
        $this->assertSame('bob@test.com', $found->email);
    }

    public function testFindReturnsNullForMissing(): void
    {
        $this->assertNull($this->em->find(TestUser::class, 999));
    }

    // --- Repository ---

    public function testRepositoryFindAll(): void
    {
        $this->createUsers(['Alice', 'Bob', 'Charlie']);

        $repo = $this->em->getRepository(TestUser::class);
        $users = $repo->findAll();

        $this->assertCount(3, $users);
    }

    public function testRepositoryFindBy(): void
    {
        $this->createUsers(['Alice', 'Bob', 'Alice']);

        $repo = $this->em->getRepository(TestUser::class);
        $alices = $repo->findBy(['name' => 'Alice']);

        $this->assertCount(2, $alices);
    }

    public function testRepositoryFindOneBy(): void
    {
        $this->createUsers(['Alice', 'Bob']);

        $repo = $this->em->getRepository(TestUser::class);
        $bob = $repo->findOneBy(['name' => 'Bob']);

        $this->assertNotNull($bob);
        $this->assertSame('Bob', $bob->name);
    }

    public function testRepositoryFindByWithOrder(): void
    {
        $this->createUsers(['Charlie', 'Alice', 'Bob']);

        $repo = $this->em->getRepository(TestUser::class);
        $users = $repo->findBy([], ['name' => 'ASC']);

        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('Bob', $users[1]->name);
        $this->assertSame('Charlie', $users[2]->name);
    }

    public function testRepositoryFindByWithLimit(): void
    {
        $this->createUsers(['A', 'B', 'C', 'D']);

        $repo = $this->em->getRepository(TestUser::class);
        $users = $repo->findBy([], null, 2);

        $this->assertCount(2, $users);
    }

    public function testRepositoryCount(): void
    {
        $this->createUsers(['A', 'B', 'C']);

        $repo = $this->em->getRepository(TestUser::class);

        $this->assertSame(3, $repo->count());
        $this->assertSame(1, $repo->count(['name' => 'B']));
    }

    // --- Update (dirty checking) ---

    public function testDirtyCheckingUpdate(): void
    {
        $user = new TestUser();
        $user->name = 'Original';
        $user->email = 'orig@test.com';

        $this->em->persist($user);
        $this->em->flush();

        // Modifier l'entité managée
        $user->name = 'Updated';
        $this->em->flush();

        // Vérifier dans la DB directement
        $row = $this->em->getConnection()->fetchOne(
            'SELECT name FROM test_users WHERE id = :id',
            ['id' => $user->id],
        );

        $this->assertSame('Updated', $row['name']);
    }

    // --- Remove ---

    public function testRemove(): void
    {
        $user = new TestUser();
        $user->name = 'ToDelete';
        $user->email = 'del@test.com';

        $this->em->persist($user);
        $this->em->flush();

        $id = $user->id;

        $this->em->remove($user);
        $this->em->flush();

        $this->assertNull($this->em->find(TestUser::class, $id));
    }

    // --- Multiple entities ---

    public function testMultipleEntities(): void
    {
        $post = new TestPost();
        $post->title = 'Hello';
        $post->body = 'World';

        $this->em->persist($post);
        $this->em->flush();

        $found = $this->em->find(TestPost::class, $post->id);

        $this->assertSame('Hello', $found->title);
        $this->assertSame('World', $found->body);
    }

    // --- Schema ---

    public function testGetClassMetadata(): void
    {
        $meta = $this->em->getClassMetadata(TestUser::class);

        $this->assertSame('test_users', $meta->tableName);
        $this->assertSame('id', $meta->idProperty);
        $this->assertSame('id', $meta->idColumn);
        $this->assertTrue($meta->idGenerated);
        $this->assertArrayHasKey('name', $meta->columns);
        $this->assertArrayHasKey('email', $meta->columns);
    }

    // --- QueryBuilder depuis l'EM ---

    public function testCreateQueryBuilder(): void
    {
        $this->createUsers(['Alice', 'Bob']);

        $qb = $this->em->createQueryBuilder();
        $rows = $qb->select('*')
            ->from('test_users')
            ->where('name = :name')
            ->setParameter('name', 'Alice')
            ->fetchAllAssociative();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // --- Identity Map ---

    public function testIdentityMapReturnsSameInstance(): void
    {
        $user = new TestUser();
        $user->name = 'Same';
        $user->email = 'same@test.com';

        $this->em->persist($user);
        $this->em->flush();

        $a = $this->em->find(TestUser::class, $user->id);
        $b = $this->em->find(TestUser::class, $user->id);

        $this->assertSame($a, $b);
    }

    // --- Helpers ---

    private function createUsers(array $names): void
    {
        foreach ($names as $name) {
            $user = new TestUser();
            $user->name = $name;
            $user->email = strtolower($name) . '@test.com';
            $this->em->persist($user);
        }
        $this->em->flush();
    }
}

// === Entités de test ===

#[Entity(table: 'test_users')]
class TestUser
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    public int $id;

    #[Column(type: 'string', length: 100)]
    public string $name;

    #[Column(type: 'string', length: 255)]
    public string $email;
}

#[Entity(table: 'test_posts')]
class TestPost
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    public int $id;

    #[Column(type: 'string', length: 200)]
    public string $title;

    #[Column(type: 'text')]
    public string $body;
}

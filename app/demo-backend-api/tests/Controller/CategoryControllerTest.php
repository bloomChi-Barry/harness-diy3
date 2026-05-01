<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CategoryControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        // Create schema from entity metadata
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception) {
            // Schema may not exist yet
        }
        $schemaTool->createSchema($metadata);

        $this->seedData();
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);

        parent::tearDown();
    }

    /**
     * Builds: Electronics -> Phones -> iPhone, Laptops (disabled), Books.
     */
    private function seedData(): void
    {
        $electronics = $this->createEntity('Electronics', 0);
        $phones = $this->createEntity('Phones', 1, $electronics);
        $this->createEntity('iPhone', 0, $phones);
        $laptops = $this->createEntity('Laptops', 2, $electronics);
        $laptops->setIsEnabled(false);
        $this->createEntity('Books', 3);
        $this->em->persist($laptops);
        $this->em->flush();
    }

    private function createEntity(string $name, int $sortOrder = 0, ?Category $parent = null): Category
    {
        $c = new Category();
        $c->setName($name);
        $c->setSortOrder($sortOrder);
        if (null !== $parent) {
            $c->setParent($parent);
            $parent->getChildren()->add($c);
        }
        $this->em->persist($c);

        return $c;
    }

    // ── GET /api/categories ────────────────────────────────────────

    public function testGetCategoriesReturnsTree(): void
    {
        $this->client->jsonRequest('GET', '/api/categories');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data, 'Should have 2 root categories');
        $this->assertSame('Electronics', $data[0]['name']);
        $this->assertArrayHasKey('children', $data[0]);
        $this->assertCount(2, $data[0]['children'], 'Electronics should have 2 children');
        $this->assertSame('Books', $data[1]['name']);
    }

    public function testGetCategoriesEnabledOnly(): void
    {
        $this->client->jsonRequest('GET', '/api/categories?enabled_only=true');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data);
        // Electronics has 2 children but Laptops is disabled
        $this->assertCount(1, $data[0]['children'], 'Disabled child should be filtered');
        $this->assertSame('Phones', $data[0]['children'][0]['name']);
    }

    // ── GET /api/categories/{id} ───────────────────────────────────

    public function testGetCategoryDetail(): void
    {
        $this->client->jsonRequest('GET', '/api/categories/1');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Electronics', $data['name']);
        $this->assertArrayHasKey('children', $data);
        $this->assertCount(2, $data['children']);
    }

    public function testGetCategoryNotFound(): void
    {
        $this->client->jsonRequest('GET', '/api/categories/999');

        self::assertResponseStatusCodeSame(404);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('NOT_FOUND', $data['error']['code']);
    }

    // ── POST /api/categories ──────────────────────────────────────

    public function testPostCreateCategory(): void
    {
        $this->client->jsonRequest('POST', '/api/categories', [
            'name' => 'Tablets',
            'parent_id' => 1,
            'sort_order' => 10,
        ]);

        self::assertResponseStatusCodeSame(201);
        $this->assertTrue($this->client->getResponse()->headers->has('Location'));
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Tablets', $data['name']);
        $this->assertNotNull($data['id']);
    }

    public function testPostCreateMissingName(): void
    {
        $this->client->jsonRequest('POST', '/api/categories', ['sort_order' => 0]);

        self::assertResponseStatusCodeSame(400);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
    }

    // ── PUT /api/categories/{id} ──────────────────────────────────

    public function testPutUpdateCategory(): void
    {
        $this->client->jsonRequest('PUT', '/api/categories/1', [
            'name' => 'Updated Electronics',
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Updated Electronics', $data['name']);
    }

    public function testPutUpdateNotFound(): void
    {
        $this->client->jsonRequest('PUT', '/api/categories/999', ['name' => 'Nope']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testPutUpdateCircularReference(): void
    {
        // Electronics (id=1) is parent of Phones (id=2)
        // Setting Electronics' parent to iPhone (id=3, which is child of Phones)
        // would make Electronics its own descendant
        $this->client->jsonRequest('PUT', '/api/categories/1', ['parent_id' => 3]);

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('CIRCULAR_REFERENCE', $data['error']['code']);
    }

    // ── DELETE /api/categories/{id} ────────────────────────────────

    public function testDeleteCategory(): void
    {
        // iPhone has no children, safe to delete
        $this->client->jsonRequest('DELETE', '/api/categories/3');

        self::assertResponseStatusCodeSame(204);
    }

    public function testDeleteCategoryNotFound(): void
    {
        $this->client->jsonRequest('DELETE', '/api/categories/999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeleteCategoryHasChildren(): void
    {
        // Electronics has children
        $this->client->jsonRequest('DELETE', '/api/categories/1');

        self::assertResponseStatusCodeSame(409);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('HAS_CHILDREN', $data['error']['code']);
    }

    public function testDeleteThenGetReturns404(): void
    {
        $this->client->jsonRequest('DELETE', '/api/categories/3');
        self::assertResponseStatusCodeSame(204);

        $this->client->jsonRequest('GET', '/api/categories/3');
        self::assertResponseStatusCodeSame(404);
    }

    // ── PATCH /api/categories/{id}/toggle ─────────────────────────

    public function testToggleEnabled(): void
    {
        $this->client->jsonRequest('PATCH', '/api/categories/1/toggle', [
            'is_enabled' => false,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['is_enabled']);
    }

    public function testToggleNotFound(): void
    {
        $this->client->jsonRequest('PATCH', '/api/categories/999/toggle', [
            'is_enabled' => true,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testToggleThenVerify(): void
    {
        $this->client->jsonRequest('PATCH', '/api/categories/2/toggle', [
            'is_enabled' => false,
        ]);
        self::assertResponseStatusCodeSame(200);

        // Verify via GET
        $this->client->jsonRequest('GET', '/api/categories/2');
        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['is_enabled']);
    }

    // ── PATCH /api/categories/{id}/move ───────────────────────────

    public function testMoveCategory(): void
    {
        // Move iPhone (id=3) from under Phones to directly under Electronics (id=1)
        $this->client->jsonRequest('PATCH', '/api/categories/3/move', [
            'parent_id' => 1,
            'sort_order' => 5,
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame(5, $data['sort_order']);
    }

    public function testMoveCategoryToRoot(): void
    {
        // Move Phones (id=2) to root level
        $this->client->jsonRequest('PATCH', '/api/categories/2/move', [
            'parent_id' => null,
            'sort_order' => 0,
        ]);

        self::assertResponseStatusCodeSame(200);
        // Verify it's at root level
        $this->client->jsonRequest('GET', '/api/categories');
        self::assertResponseStatusCodeSame(200);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(3, $data, 'Should now have 3 root categories');
    }

    public function testMoveNotFound(): void
    {
        $this->client->jsonRequest('PATCH', '/api/categories/999/move', [
            'parent_id' => 1,
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testMoveCircularReference(): void
    {
        // Electronics (id=1) is parent of Phones (id=2)
        // Phones (id=2) is parent of iPhone (id=3)
        // Moving Electronics (id=1) under iPhone (id=3) creates a cycle
        $this->client->jsonRequest('PATCH', '/api/categories/1/move', [
            'parent_id' => 3,
        ]);

        self::assertResponseStatusCodeSame(422);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('CIRCULAR_REFERENCE', $data['error']['code']);
    }
}

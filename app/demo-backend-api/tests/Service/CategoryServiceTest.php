<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CategoryInput;
use App\Entity\Category;
use App\Exception\CategoryHasChildrenException;
use App\Exception\CategoryNotFoundException;
use App\Exception\CircularReferenceException;
use App\Service\CategoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    /** @var EntityRepository<Category> */
    private EntityRepository $repo;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repo = $this->createMock(EntityRepository::class);

        $this->em->method('getRepository')
            ->with(Category::class)
            ->willReturn($this->repo);

        $this->service = new CategoryService($this->em);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function createCategory(string $name, ?int $id = null): Category
    {
        $c = new Category();
        $c->setName($name);
        if (null !== $id) {
            (new \ReflectionProperty(Category::class, 'id'))->setValue($c, $id);
        }

        return $c;
    }

    private function addChild(Category $parent, Category $child): void
    {
        $child->setParent($parent);
        $parent->getChildren()->add($child);
    }

    // ── getTree ──────────────────────────────────────────────────

    public function testGetTreeReturnsNestedStructure(): void
    {
        $root = $this->createCategory('Electronics', 1);
        $child = $this->createCategory('Phones', 2);
        $grandchild = $this->createCategory('iPhone', 3);
        $this->addChild($root, $child);
        $this->addChild($child, $grandchild);

        $this->repo->method('findBy')
            ->with([], ['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->willReturn([$root, $child, $grandchild]);

        $tree = $this->service->getTree();

        $this->assertCount(1, $tree);
        $this->assertSame('Electronics', $tree[0]['name']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Phones', $tree[0]['children'][0]['name']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
        $this->assertSame('iPhone', $tree[0]['children'][0]['children'][0]['name']);
    }

    public function testGetTreeEnabledOnlyFiltersDisabled(): void
    {
        $enabled = $this->createCategory('Enabled', 1);
        $disabled = $this->createCategory('Disabled', 2);
        $disabled->setIsEnabled(false);

        $this->repo->method('findBy')
            ->with([], ['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->willReturn([$enabled, $disabled]);

        $tree = $this->service->getTree(enabledOnly: true);

        $this->assertCount(1, $tree);
        $this->assertSame('Enabled', $tree[0]['name']);
    }

    public function testGetTreeEnabledOnlyFiltersDisabledChildren(): void
    {
        $root = $this->createCategory('Root', 1);
        $enabledChild = $this->createCategory('Visible', 2);
        $disabledChild = $this->createCategory('Hidden', 3);
        $disabledChild->setIsEnabled(false);
        $this->addChild($root, $enabledChild);
        $this->addChild($root, $disabledChild);

        $this->repo->method('findBy')
            ->with([], ['sortOrder' => 'ASC', 'id' => 'ASC'])
            ->willReturn([$root, $enabledChild, $disabledChild]);

        $tree = $this->service->getTree(enabledOnly: true);

        $this->assertCount(1, $tree);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Visible', $tree[0]['children'][0]['name']);
    }

    // ── getById ──────────────────────────────────────────────────

    public function testGetByIdReturnsCategoryWithDirectChildren(): void
    {
        $parent = $this->createCategory('Parent', 1);
        $child = $this->createCategory('Child', 2);
        $this->addChild($parent, $child);

        $this->repo->method('find')
            ->with(1)
            ->willReturn($parent);

        $result = $this->service->getById(1);

        $this->assertSame('Parent', $result['name']);
        $this->assertCount(1, $result['children']);
        $this->assertSame('Child', $result['children'][0]['name']);
    }

    public function testGetByIdThrowsNotFoundException(): void
    {
        $this->repo->method('find')->with(99)->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);
        $this->service->getById(99);
    }

    // ── create ───────────────────────────────────────────────────

    public function testCreatePersistsCategory(): void
    {
        $input = new CategoryInput(name: 'New Category', sortOrder: 5);

        $this->em->expects($this->once())->method('persist')
            ->with($this->callback(function (Category $c) {
                return 'New Category' === $c->getName() && 5 === $c->getSortOrder();
            }));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->create($input);

        $this->assertSame('New Category', $result->getName());
        $this->assertSame(5, $result->getSortOrder());
    }

    public function testCreateWithParentId(): void
    {
        $parent = $this->createCategory('Parent', 1);
        $this->repo->method('find')->with(1)->willReturn($parent);

        $input = new CategoryInput(name: 'Child', parentId: 1);

        $result = $this->service->create($input);

        $this->assertSame($parent, $result->getParent());
    }

    public function testCreateWithEmptyNameThrowsException(): void
    {
        $input = new CategoryInput(name: '');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Category name is required');
        $this->service->create($input);
    }

    public function testCreateWithNullNameThrowsException(): void
    {
        $input = new CategoryInput(name: null);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->create($input);
    }

    // ── update ───────────────────────────────────────────────────

    public function testUpdateChangesFields(): void
    {
        $category = $this->createCategory('Old Name', 1);
        $category->setSortOrder(10);
        $this->repo->method('find')->with(1)->willReturn($category);

        $input = new CategoryInput(name: 'New Name', sortOrder: 20);
        $result = $this->service->update(1, $input);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame(20, $result->getSortOrder());
    }

    public function testUpdateCircularReferenceThrowsException(): void
    {
        // Category 1 is parent of Category 3. Category 2 is child of Category 3.
        // Attempting to set Category 1's parent to 2 makes 1 a child of its own descendant.
        $root = $this->createCategory('Root', 1);
        $child = $this->createCategory('Child', 3);
        $grandchild = $this->createCategory('Grandchild', 2);
        $this->addChild($root, $child);
        $this->addChild($child, $grandchild);

        $this->repo->method('find')
            ->willReturnMap([
                [1, null, null, $root],
                [2, null, null, $grandchild],
            ]);

        $input = new CategoryInput(parentId: 2);

        $this->expectException(CircularReferenceException::class);
        $this->service->update(1, $input);
    }

    public function testUpdateSelfParentThrowsCircularReference(): void
    {
        $category = $this->createCategory('Self', 1);
        $this->repo->method('find')->with(1)->willReturn($category);

        $input = new CategoryInput(parentId: 1);

        $this->expectException(CircularReferenceException::class);
        $this->service->update(1, $input);
    }

    // ── delete ───────────────────────────────────────────────────

    public function testDeleteRemovesCategoryWithNoChildren(): void
    {
        $category = $this->createCategory('Solo', 1);
        $this->repo->method('find')->with(1)->willReturn($category);

        $this->em->expects($this->once())->method('remove')->with($category);
        $this->em->expects($this->once())->method('flush');

        $this->service->delete(1);

        $this->addToAssertionCount(1);
    }

    public function testDeleteWithChildrenThrowsException(): void
    {
        $parent = $this->createCategory('Parent', 1);
        $child = $this->createCategory('Child', 2);
        $this->addChild($parent, $child);
        $this->repo->method('find')->with(1)->willReturn($parent);

        $this->expectException(CategoryHasChildrenException::class);
        $this->service->delete(1);
    }

    // ── toggle ───────────────────────────────────────────────────

    public function testToggleEnablesDisabledCategory(): void
    {
        $category = $this->createCategory('Toggle', 1);
        $category->setIsEnabled(false);
        $this->repo->method('find')->with(1)->willReturn($category);

        $result = $this->service->toggle(1, true);

        $this->assertTrue($result->isEnabled());
    }

    public function testToggleDisablesEnabledCategory(): void
    {
        $category = $this->createCategory('Toggle', 1);
        $category->setIsEnabled(true);
        $this->repo->method('find')->with(1)->willReturn($category);

        $result = $this->service->toggle(1, false);

        $this->assertFalse($result->isEnabled());
    }

    // ── move ─────────────────────────────────────────────────────

    public function testMoveChangesParentAndSortOrder(): void
    {
        $category = $this->createCategory('Mover', 1);
        $newParent = $this->createCategory('NewParent', 2);
        $this->repo->method('find')
            ->willReturnMap([
                [1, null, null, $category],
                [2, null, null, $newParent],
            ]);

        $result = $this->service->move(1, 2, 99);

        $this->assertSame($newParent, $result->getParent());
        $this->assertSame(99, $result->getSortOrder());
    }

    public function testMoveToNullParentMakesRoot(): void
    {
        $parent = $this->createCategory('OldParent', 2);
        $category = $this->createCategory('Orphan', 1);
        $this->addChild($parent, $category);
        $this->repo->method('find')
            ->willReturnMap([
                [1, null, null, $category],
            ]);

        $result = $this->service->move(1, null, 0);

        $this->assertNull($result->getParent());
    }

    public function testMoveCircularReferenceThrowsException(): void
    {
        // Category 1 is parent of Category 3. Category 2 is child of Category 3.
        // Attempting to move 1 under 2 makes it a child of its own descendant.
        $root = $this->createCategory('Root', 1);
        $child = $this->createCategory('Child', 3);
        $grandchild = $this->createCategory('Grandchild', 2);
        $this->addChild($root, $child);
        $this->addChild($child, $grandchild);

        $this->repo->method('find')
            ->willReturnMap([
                [1, null, null, $root],
                [2, null, null, $grandchild],
            ]);

        $this->expectException(CircularReferenceException::class);
        $this->service->move(1, 2, 0);
    }
}

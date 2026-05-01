<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CategoryInput;
use App\Entity\Category;
use App\Exception\CategoryHasChildrenException;
use App\Exception\CategoryNotFoundException;
use App\Exception\CircularReferenceException;
use App\Repository\CategoryRepositoryInterface;
use App\Service\CategoryService;
use PHPUnit\Framework\TestCase;

class CategoryServiceTest extends TestCase
{
    private CategoryRepositoryInterface $repository;
    private CategoryService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CategoryRepositoryInterface::class);
        $this->service = new CategoryService($this->repository);
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

        $this->repository->method('findAllOrdered')
            ->willReturn([$root, $child, $grandchild]);

        $tree = $this->service->getTree();

        $this->assertCount(1, $tree);
        $this->assertSame('Electronics', $tree[0]['name']);
        $this->assertNull($tree[0]['parent_id']);
        $this->assertTrue($tree[0]['is_enabled']);
        $this->assertIsArray($tree[0]['children']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Phones', $tree[0]['children'][0]['name']);
        $this->assertSame(1, $tree[0]['children'][0]['parent_id']);
        $this->assertCount(1, $tree[0]['children'][0]['children']);
        $this->assertSame('iPhone', $tree[0]['children'][0]['children'][0]['name']);
        $this->assertSame(2, $tree[0]['children'][0]['children'][0]['parent_id']);
    }

    public function testGetTreeEnabledOnlyFiltersDisabled(): void
    {
        $enabled = $this->createCategory('Enabled', 1);
        $disabled = $this->createCategory('Disabled', 2);
        $disabled->setIsEnabled(false);

        $this->repository->method('findAllOrdered')
            ->willReturn([$enabled, $disabled]);

        $tree = $this->service->getTree(enabledOnly: true);

        $this->assertCount(1, $tree);
        $this->assertSame('Enabled', $tree[0]['name']);
        $this->assertTrue($tree[0]['is_enabled']);
        $this->assertEmpty($tree[0]['children']);
    }

    public function testGetTreeEnabledOnlyFiltersDisabledChildren(): void
    {
        $root = $this->createCategory('Root', 1);
        $enabledChild = $this->createCategory('Visible', 2);
        $disabledChild = $this->createCategory('Hidden', 3);
        $disabledChild->setIsEnabled(false);
        $this->addChild($root, $enabledChild);
        $this->addChild($root, $disabledChild);

        $this->repository->method('findAllOrdered')
            ->willReturn([$root, $enabledChild, $disabledChild]);

        $tree = $this->service->getTree(enabledOnly: true);

        $this->assertCount(1, $tree);
        $this->assertSame('Root', $tree[0]['name']);
        $this->assertNull($tree[0]['parent_id']);
        $this->assertCount(1, $tree[0]['children']);
        $this->assertSame('Visible', $tree[0]['children'][0]['name']);
        $this->assertTrue($tree[0]['children'][0]['is_enabled']);
    }

    // ── getById ──────────────────────────────────────────────────

    public function testGetByIdReturnsCategoryWithDirectChildren(): void
    {
        $parent = $this->createCategory('Parent', 1);
        $child = $this->createCategory('Child', 2);
        $this->addChild($parent, $child);

        $this->repository->method('findById')
            ->with(1)
            ->willReturn($parent);

        $result = $this->service->getById(1);

        $this->assertSame('Parent', $result['name']);
        $this->assertSame(1, $result['id']);
        $this->assertNull($result['parent_id']);
        $this->assertCount(1, $result['children']);
        $this->assertSame('Child', $result['children'][0]['name']);
        $this->assertSame(2, $result['children'][0]['id']);
    }

    public function testGetByIdThrowsNotFoundException(): void
    {
        $this->repository->method('findById')->with(99)->willReturn(null);

        $this->expectException(CategoryNotFoundException::class);
        $this->service->getById(99);
    }

    // ── create ───────────────────────────────────────────────────

    public function testCreatePersistsCategory(): void
    {
        $input = new CategoryInput(name: 'New Category', sortOrder: 5);

        $this->repository->expects($this->once())->method('save')
            ->with($this->callback(function (Category $c) {
                return 'New Category' === $c->getName() && 5 === $c->getSortOrder();
            }));

        $result = $this->service->create($input);

        $this->assertSame('New Category', $result->getName());
        $this->assertSame(5, $result->getSortOrder());
        $this->assertTrue($result->isEnabled());
        $this->assertNull($result->getParent());
    }

    public function testCreateWithParentId(): void
    {
        $parent = $this->createCategory('Parent', 1);
        $this->repository->method('findById')->with(1)->willReturn($parent);

        $input = new CategoryInput(name: 'Child', parentId: 1);

        $result = $this->service->create($input);

        $this->assertSame($parent, $result->getParent());
        $this->assertSame('Child', $result->getName());
        $this->assertSame(0, $result->getSortOrder());
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
        $this->repository->method('findById')->with(1)->willReturn($category);

        $input = new CategoryInput(name: 'New Name', sortOrder: 20);
        $result = $this->service->update(1, $input);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame(20, $result->getSortOrder());
        $this->assertSame(1, $result->getId());
    }

    public function testUpdateCircularReferenceThrowsException(): void
    {
        $root = $this->createCategory('Root', 1);
        $child = $this->createCategory('Child', 3);
        $grandchild = $this->createCategory('Grandchild', 2);
        $this->addChild($root, $child);
        $this->addChild($child, $grandchild);

        $this->repository->method('findById')
            ->willReturnMap([
                [1, $root],
                [2, $grandchild],
            ]);
        $this->repository->method('findAncestorIds')
            ->with(2)
            ->willReturn([3, 1]);

        $input = new CategoryInput(parentId: 2);

        $this->expectException(CircularReferenceException::class);
        $this->service->update(1, $input);
    }

    public function testUpdateSelfParentThrowsCircularReference(): void
    {
        $category = $this->createCategory('Self', 1);
        $this->repository->method('findById')->with(1)->willReturn($category);

        $input = new CategoryInput(parentId: 1);

        $this->expectException(CircularReferenceException::class);
        $this->service->update(1, $input);
    }

    // ── delete ───────────────────────────────────────────────────

    public function testDeleteRemovesCategoryWithNoChildren(): void
    {
        $category = $this->createCategory('Solo', 1);
        $this->repository->method('findById')->with(1)->willReturn($category);

        $this->repository->expects($this->once())->method('remove')->with($category);

        $this->service->delete(1);

        $this->assertTrue($category->getChildren()->isEmpty());
        $this->addToAssertionCount(1);
    }

    public function testDeleteWithChildrenThrowsException(): void
    {
        $parent = $this->createCategory('Parent', 1);
        $child = $this->createCategory('Child', 2);
        $this->addChild($parent, $child);
        $this->repository->method('findById')->with(1)->willReturn($parent);

        $this->expectException(CategoryHasChildrenException::class);
        $this->service->delete(1);
    }

    // ── toggle ───────────────────────────────────────────────────

    public function testToggleEnablesDisabledCategory(): void
    {
        $category = $this->createCategory('Toggle', 1);
        $category->setIsEnabled(false);
        $this->repository->method('findById')->with(1)->willReturn($category);

        $result = $this->service->toggle(1, true);

        $this->assertTrue($result->isEnabled());
        $this->assertSame('Toggle', $result->getName());
    }

    public function testToggleDisablesEnabledCategory(): void
    {
        $category = $this->createCategory('Toggle', 1);
        $category->setIsEnabled(true);
        $this->repository->method('findById')->with(1)->willReturn($category);

        $result = $this->service->toggle(1, false);

        $this->assertFalse($result->isEnabled());
        $this->assertSame('Toggle', $result->getName());
    }

    // ── move ─────────────────────────────────────────────────────

    public function testMoveChangesParentAndSortOrder(): void
    {
        $category = $this->createCategory('Mover', 1);
        $newParent = $this->createCategory('NewParent', 2);
        $this->repository->method('findById')
            ->willReturnMap([
                [1, $category],
                [2, $newParent],
            ]);

        $result = $this->service->move(1, 2, 99);

        $this->assertSame($newParent, $result->getParent());
        $this->assertSame(99, $result->getSortOrder());
        $this->assertSame('Mover', $result->getName());
    }

    public function testMoveToNullParentMakesRoot(): void
    {
        $parent = $this->createCategory('OldParent', 2);
        $category = $this->createCategory('Orphan', 1);
        $this->addChild($parent, $category);
        $this->repository->method('findById')
            ->willReturnMap([
                [1, $category],
            ]);

        $result = $this->service->move(1, null, 0);

        $this->assertNull($result->getParent());
        $this->assertSame(0, $result->getSortOrder());
        $this->assertSame('Orphan', $result->getName());
    }

    public function testMoveCircularReferenceThrowsException(): void
    {
        $root = $this->createCategory('Root', 1);
        $child = $this->createCategory('Child', 3);
        $grandchild = $this->createCategory('Grandchild', 2);
        $this->addChild($root, $child);
        $this->addChild($child, $grandchild);

        $this->repository->method('findById')
            ->willReturnMap([
                [1, $root],
                [2, $grandchild],
            ]);
        $this->repository->method('findAncestorIds')
            ->with(2)
            ->willReturn([3, 1]);

        $this->expectException(CircularReferenceException::class);
        $this->service->move(1, 2, 0);
    }
}

<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases\Tests\Unit\Repository;

use FoF\Releases\Repository\ReleaseRepository;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class ReleaseRepositoryTest extends TestCase
{
    /**
     * Build a UserRepository mock whose query() returns a builder stub that
     * yields an empty collection for any whereIn()->get() call.
     */
    private function mockUsersEmpty(): \Flarum\User\UserRepository
    {
        return $this->mockUsersReturning([]);
    }

    /**
     * Build a UserRepository mock whose query() returns a builder stub that
     * yields the given users for any whereIn()->get() call.
     *
     * @param object[] $userObjects
     */
    private function mockUsersReturning(array $userObjects): \Flarum\User\UserRepository
    {
        // whereIn() is proxied via __call on Eloquent Builder, so it must be added via addMethods().
        // get() is a real declared method, so it is mocked via onlyMethods() (the default path).
        $queryBuilder = $this->getMockBuilder(\Illuminate\Database\Eloquent\Builder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->addMethods(['whereIn'])
            ->getMock();
        $queryBuilder->method('whereIn')->willReturnSelf();
        $queryBuilder->method('get')->willReturn(new Collection($userObjects));

        $users = $this->createMock(\Flarum\User\UserRepository::class);
        $users->method('query')->willReturn($queryBuilder);

        return $users;
    }

    public function test_format_release_content_includes_all_fields(): void
    {
        $settings = $this->createMock(\Flarum\Settings\SettingsRepositoryInterface::class);
        $settings->method('get')->willReturn(null);

        $repository = new ReleaseRepository(
            $this->createMock(\Flarum\Extension\ExtensionManager::class),
            $settings,
            $this->mockUsersEmpty(),
            $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class)
        );

        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('formatReleaseContent');
        $method->setAccessible(true);

        $result = $method->invoke(
            $repository,
            'Test changelog',
            'v1.0.0',
            'https://github.com/test/repo/releases/tag/v1.0.0',
            'testuser'
        );

        $this->assertStringContainsString('v1.0.0', $result);
        $this->assertStringContainsString('testuser', $result);
        $this->assertStringContainsString('https://github.com/test/repo/releases/tag/v1.0.0', $result);
        $this->assertStringContainsString('Test changelog', $result);
        $this->assertStringContainsString('----', $result);
    }

    public function test_format_release_content_handles_empty_optional_fields(): void
    {
        $settings = $this->createMock(\Flarum\Settings\SettingsRepositoryInterface::class);
        $settings->method('get')->willReturn(null);

        $repository = new ReleaseRepository(
            $this->createMock(\Flarum\Extension\ExtensionManager::class),
            $settings,
            $this->mockUsersEmpty(),
            $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class)
        );

        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('formatReleaseContent');
        $method->setAccessible(true);

        $result = $method->invoke(
            $repository,
            'Changelog only',
            'v2.0.0',
            '',
            ''
        );

        $this->assertStringContainsString('v2.0.0', $result);
        $this->assertStringContainsString('Changelog only', $result);
        $this->assertStringNotContainsString('**Released by:**', $result);
        $this->assertStringNotContainsString('**Release URL:**', $result);
    }

    public function test_username_mapping_replaces_in_author_and_content(): void
    {
        $settings = $this->createMock(\Flarum\Settings\SettingsRepositoryInterface::class);
        $settings->method('get')
            ->with('fof-releases.username_mappings')
            ->willReturn(json_encode([['platform' => 'imorland', 'forum' => 'ianm']]));

        $user = (object) [
            'id' => 1,
            'username' => 'ianm',
            'display_name' => 'IanM',
        ];

        $repository = new ReleaseRepository(
            $this->createMock(\Flarum\Extension\ExtensionManager::class),
            $settings,
            $this->mockUsersReturning([$user]),
            $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class)
        );

        $reflection = new \ReflectionClass($repository);
        $method = $reflection->getMethod('formatReleaseContent');
        $method->setAccessible(true);

        $result = $method->invoke(
            $repository,
            'Thanks to imorland for the fix. Also @imorland contributed.',
            'v1.0.0',
            'https://example.com',
            'imorland'
        );

        $this->assertStringContainsString('**Released by:** @"IanM"#1', $result);
        $this->assertStringContainsString('Thanks to @"IanM"#1 for the fix', $result);
        $this->assertStringContainsString('Also @"IanM"#1 contributed.', $result);
    }
}

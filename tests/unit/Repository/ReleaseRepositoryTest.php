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
use PHPUnit\Framework\TestCase;

class ReleaseRepositoryTest extends TestCase
{
    public function test_format_release_content_includes_all_fields(): void
    {
        $repository = new ReleaseRepository(
            $this->createMock(\Flarum\Extension\ExtensionManager::class)
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
        $this->assertStringContainsString('Changelog', $result);
    }

    public function test_format_release_content_handles_empty_optional_fields(): void
    {
        $repository = new ReleaseRepository(
            $this->createMock(\Flarum\Extension\ExtensionManager::class)
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
        $this->assertStringNotContainsString('**Author:**', $result);
        $this->assertStringNotContainsString('**Release URL:**', $result);
    }
}

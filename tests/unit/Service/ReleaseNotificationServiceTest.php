<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases\Tests\Unit\Service;

use Flarum\Discussion\Discussion;
use Flarum\Http\AccessToken;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\Releases\Service\ReleaseNotificationService;
use Illuminate\Contracts\Translation\Translator;
use Mockery as m;
use PHPUnit\Framework\TestCase;

class ReleaseNotificationServiceTest extends TestCase
{
    protected ReleaseNotificationService $service;
    protected SettingsRepositoryInterface $settings;
    protected Translator $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = m::mock(SettingsRepositoryInterface::class);
        $this->translator = m::mock(Translator::class);

        $this->service = new ReleaseNotificationService(
            $this->settings,
            $this->translator
        );
    }

    protected function tearDown(): void
    {
        m::close();
        parent::tearDown();
    }

    public function testAuthenticateByTokenReturnsUserWhenTokenIsValid(): void
    {
        $token = 'valid-token-123';
        $user = m::mock(User::class);

        $accessToken = m::mock(AccessToken::class);
        $accessToken->user = $user;

        AccessToken::shouldReceive('findValid')
            ->with($token)
            ->once()
            ->andReturn($accessToken);

        $result = $this->service->authenticateByToken($token);

        $this->assertSame($user, $result);
    }

    public function testAuthenticateByTokenReturnsNullWhenTokenIsInvalid(): void
    {
        AccessToken::shouldReceive('findValid')
            ->with('invalid-token')
            ->once()
            ->andReturn(null);

        $result = $this->service->authenticateByToken('invalid-token');

        $this->assertNull($result);
    }

    public function testGetDiscussionReturnsDiscussionWhenFound(): void
    {
        $discussionId = 1;
        $discussion = m::mock(Discussion::class);

        Discussion::shouldReceive('find')
            ->with($discussionId)
            ->once()
            ->andReturn($discussion);

        $result = $this->service->getDiscussion($discussionId);

        $this->assertSame($discussion, $result);
    }

    public function testGetDiscussionReturnsNullWhenNotFound(): void
    {
        Discussion::shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->getDiscussion(999);

        $this->assertNull($result);
    }

    public function testBuildReleasePostContentWithAllParameters(): void
    {
        $this->translator->shouldReceive('trans')
            ->with('fof-releases.forum.post.header_with_author', ['version' => 'v1.0.0', 'author' => '@testuser'])
            ->once()
            ->andReturn('🚀 **Version v1.0.0** has been released by @testuser!');

        $this->translator->shouldReceive('trans')
            ->with('fof-releases.forum.post.changelog_heading')
            ->once()
            ->andReturn('What\'s Changed');

        $this->translator->shouldReceive('trans')
            ->with('fof-releases.forum.post.view_release', ['url' => 'https://github.com/test/repo/releases/tag/v1.0.0'])
            ->once()
            ->andReturn('[**View full release →**](https://github.com/test/repo/releases/tag/v1.0.0)');

        $this->translator->shouldReceive('trans')
            ->with('fof-releases.forum.post.repository', ['name' => 'test/repo'])
            ->once()
            ->andReturn('**Repository:** test/repo');

        $this->settings->shouldReceive('get')
            ->with('fof-releases.username_mappings', '{}')
            ->once()
            ->andReturn('{}');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildReleasePostContent');
        $method->setAccessible(true);

        $result = $method->invoke(
            $this->service,
            'Test changelog content',
            'v1.0.0',
            'https://github.com/test/repo/releases/tag/v1.0.0',
            'test/repo',
            'testuser'
        );

        $this->assertStringContainsString('🚀 **Version v1.0.0**', $result);
        $this->assertStringContainsString('Test changelog content', $result);
        $this->assertStringContainsString('View full release', $result);
        $this->assertStringContainsString('test/repo', $result);
    }

    public function testMapUsernameWithExistingMapping(): void
    {
        $mappings = [
            'github_user' => 'flarum_user',
            'another_github' => 'another_flarum'
        ];

        $this->settings->shouldReceive('get')
            ->with('fof-releases.username_mappings', '{}')
            ->once()
            ->andReturn(json_encode($mappings));

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('mapUsername');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'github_user');

        $this->assertEquals('flarum_user', $result);
    }

    public function testMapUsernameWithoutMappingReturnsOriginal(): void
    {
        $this->settings->shouldReceive('get')
            ->with('fof-releases.username_mappings', '{}')
            ->once()
            ->andReturn('{}');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('mapUsername');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'unmapped_user');

        $this->assertEquals('unmapped_user', $result);
    }

    public function testMapUsernameWithNullReturnsNull(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('mapUsername');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, null);

        $this->assertNull($result);
    }
}

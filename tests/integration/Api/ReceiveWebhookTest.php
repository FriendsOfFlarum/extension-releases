<?php

/*
 * This file is part of fof/extension-releases.
 *
 * Copyright (c) 2026 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Releases\Tests\Integration\Api;

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class ReceiveWebhookTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-extension-releases');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'Test Discussion', 'slug' => '1-test-discussion', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1, 'last_posted_at' => Carbon::now(), 'last_posted_user_id' => 2, 'last_post_number' => 1],
            ],
            'posts' => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First post content</p></t>'],
            ],
            'group_permission' => [
                ['group_id' => 3, 'permission' => 'fof-releases.publishReleaseUpdates'],
                ['group_id' => 3, 'permission' => 'postWithoutThrottle'],
            ],
        ]);
    }

    /**
     * Extract error message from JSON:API or simple error response.
     */
    private function getErrorMessage(array $body): string
    {
        if (isset($body['errors'][0]['detail'])) {
            return $body['errors'][0]['detail'];
        }
        if (isset($body['error'])) {
            return $body['error'];
        }
        return json_encode($body);
    }

    /**
     * @test
     */
    public function webhook_endpoint_exists(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [],
            ])
        );

        $this->assertNotEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function webhook_returns_422_when_missing_required_fields(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'authenticatedAs' => 2,
                'json' => [
                    // Missing discussion_id, changelog, tag_name
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertStringContainsString('Missing required fields', $this->getErrorMessage($body));
    }

    /**
     * @test
     */
    public function webhook_returns_error_when_not_authenticated(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'json' => [
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        // Guest gets 400 (CSRF) or 403 (permission denied)
        $this->assertContains($response->getStatusCode(), [400, 403]);
    }

    /**
     * @test
     */
    public function webhook_returns_404_when_discussion_not_found(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'authenticatedAs' => 2,
                'json' => [
                    'discussion_id' => 999,
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function webhook_creates_post_with_valid_data(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'authenticatedAs' => 2,
                'json' => [
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog content',
                    'tag_name' => 'v1.0.0',
                    'release_url' => 'https://github.com/test/repo/releases/tag/v1.0.0',
                    'author' => 'testuser',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertTrue($body['success']);
        $this->assertArrayHasKey('post_id', $body);
        $this->assertArrayHasKey('post_number', $body);

        $this->assertNotNull(\Flarum\Post\Post::find($body['post_id']));
    }

    /**
     * @test
     */
    public function webhook_returns_403_when_user_lacks_publish_permission(): void
    {
        $this->database()->table('group_permission')
            ->where('permission', 'fof-releases.publishReleaseUpdates')
            ->delete();

        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'authenticatedAs' => 2,
                'json' => [
                    'discussion_id' => 1,
                    'changelog' => 'Test changelog',
                    'tag_name' => 'v1.0.0',
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function webhook_post_contains_expected_content(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/fof/releases/webhook', [
                'authenticatedAs' => 2,
                'json' => [
                    'discussion_id' => 1,
                    'changelog' => "- Fixed bug #123\n- Added new feature",
                    'tag_name' => 'v2.5.0',
                    'release_url' => 'https://github.com/fof/example/releases/tag/v2.5.0',
                    'author' => 'johndoe',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $post = \Flarum\Post\Post::find($body['post_id']);

        $this->assertStringContainsString('v2.5.0', $post->content);
        $this->assertStringContainsString('Fixed bug #123', $post->content);
        $this->assertStringContainsString('Added new feature', $post->content);
    }
}

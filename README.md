# FoF Extension Releases

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/extension-releases.svg)](https://packagist.org/packages/fof/extension-releases) [![Total Downloads](https://img.shields.io/packagist/dt/fof/extension-releases.svg)](https://packagist.org/packages/fof/extension-releases)

A [Flarum](https://flarum.org) 2.0 extension that automatically posts release notifications to discussions when you publish a new GitHub or GitLab release.

## Features

- 🚀 Automatic release notifications posted to Flarum discussions
- 🔐 Secure webhook endpoint with Flarum API token authentication (Authorization header)
- ✅ Full test coverage (unit and integration tests)
- 🔧 Support for both GitHub Actions and GitLab CI/CD
- ✅ Auto-approves posts when flarum/approval is enabled

## Installation

Install with composer:

```sh
composer require fof/extension-releases
```

Then enable the extension in your Flarum admin panel.

## Configuration

### Step 1: Generate a Flarum API Token

You need an API token for the user who will post the release notifications:

**Prerequisites:**
- The user must have the **"Create access token"** permission (found in **Permissions** → **Moderate** section)
- The user must have the **"Publish release updates via webhook"** permission (found in **Permissions** → **Start Discussions** section)

**Generate Token:**

1. Log in to Flarum as the user who should post releases (e.g., a bot account)
2. Go to **Settings** → **API Tokens** (or use a REST client)
3. Generate a new API token
4. Copy the token - you'll need it for the webhook configuration

Alternatively, you can generate a token programmatically:

```bash
# Using the Flarum API
curl -X POST https://your-flarum-site.com/api/token \
  -H "Content-Type: application/json" \
  -d '{
    "identification": "your-username",
    "password": "your-password",
    "lifetime": 31536000
  }'
```

**Note:** Make sure to grant both required permissions to the user in the admin panel before attempting to use the webhook.

### Step 2: Setup GitHub Webhook

#### Option A: Using GitHub Actions (Recommended)

The workflow automatically reads the discussion ID and Flarum URL from your `composer.json` file's `support.forum` field!

**Setup:**

1. Ensure your `composer.json` has the `support.forum` field:
   ```json
   {
     "support": {
       "forum": "https://discuss.flarum.org/d/12345-your-extension-discussion"
     }
   }
   ```

2. Copy `.github/workflows/flarum-release-notification.example.yml` to `.github/workflows/flarum-release-notification.yml`

3. Add your API token secret:
   - Go to your GitHub repository → **Settings** → **Secrets and variables** → **Actions**
   - Add secret: `FLARUM_EXTENSION_UPDATES_API_TOKEN` with your Flarum API token from Step 2

That's it! The workflow will automatically:
- Extract the discussion ID from `support.forum` in `composer.json`
- Extract the Flarum site URL
- Use the GitHub release body as the changelog
- Post to your discussion whenever you publish a release

#### Option B: Using GitHub Webhooks (Manual)

1. Go to your GitHub repository → **Settings** → **Webhooks** → **Add webhook**
2. Configure:
   - **Payload URL**: `https://your-flarum-site.com/api/fof/releases/webhook`
   - **Content type**: `application/json`
   - **Events**: Select "Releases" only
   - **Active**: ✓ Checked

**Note:** Standard GitHub webhooks don't include the API token or discussion ID. You'll need a middleware service or use GitHub Actions instead.

### Step 3: Setup GitLab Webhook

#### Option A: Using GitLab CI/CD (Recommended)

The pipeline automatically reads the discussion ID and Flarum URL from your `composer.json` file's `support.forum` field!

**Setup:**

1. Ensure your `composer.json` has the `support.forum` field:
   ```json
   {
     "support": {
       "forum": "https://discuss.flarum.org/d/12345-your-extension-discussion"
     }
   }
   ```

2. Copy `.gitlab-ci.example.yml` to `.gitlab-ci.yml` in your repository

3. Add your API token variable:
   - Go to your GitLab repository → **Settings** → **CI/CD** → **Variables**
   - Add variable: `FLARUM_EXTENSION_UPDATES_API_TOKEN` with your Flarum API token (mark as protected and masked)

That's it! The pipeline will automatically:
- Extract the discussion ID from `support.forum` in `composer.json`
- Extract the Flarum site URL
- Use the Git tag message as the changelog
- Post to your discussion whenever you create a new tag

#### Option B: Using GitLab Webhooks

1. Go to your GitLab repository → **Settings** → **Webhooks**
2. Add webhook:
   - **URL**: `https://your-flarum-site.com/api/fof/releases/webhook`
   - **Trigger**: Check "Releases events"
   - **Enable SSL verification**: ✓ Checked

**Note:** Like GitHub, standard GitLab webhooks require middleware. Use GitLab CI/CD for direct integration.

## API Reference

### Webhook Endpoint

**POST** `/api/fof/releases/webhook`

**Authentication:** Use Flarum's standard API authentication via the `Authorization` header:

```
Authorization: Token YOUR_FLARUM_EXTENSION_UPDATES_API_TOKEN
```

**Request Body:**

```json
{
  "discussion_id": 123,
  "changelog": "## What's Changed\n\n- Fixed bug #42\n- Added new feature",
  "tag_name": "v1.0.0",
  "release_url": "https://github.com/owner/repo/releases/tag/v1.0.0",
  "author": "octocat"
}
```

| Field | Required | Description |
|-------|----------|-------------|
| `discussion_id` | Yes | Discussion ID to post to |
| `changelog` | Yes | Release notes/changelog (Markdown supported) |
| `tag_name` | Yes | Version tag (e.g., v1.0.0) |
| `release_url` | No | URL to the release page |
| `author` | No | GitHub/GitLab username of release author |

**Responses:**

- `201 Created` - Post created successfully
  ```json
  {
    "success": true,
    "post_id": 123,
    "post_number": 5
  }
  ```

- `403 Forbidden` - Not authenticated or lacks permission
- `404 Not Found` - Discussion not found
- `422 Unprocessable Entity` - Missing required fields (discussion_id, changelog, or tag_name)

## Post Format

Release posts use this structure:

```
## 🚀 New Release: {tag_name}

**Author:** {author}
**Release URL:** {release_url}

### Changelog

{changelog}
```

Optional fields (author, release_url) are omitted when not provided.

## Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run integration tests only
composer test:integration

# Setup integration test database (run once)
composer test:setup
```

## Troubleshooting

### Webhook returns 401 Unauthorized

- Verify your API token is valid and hasn't expired
- Ensure the user associated with the token still has an active account

### Webhook returns 403 Forbidden

- Check that the user has permission to reply to the discussion
- Verify the discussion isn't locked

### Posts still require approval (flarum/approval)

This extension creates posts directly (bypassing the API) and sets `is_approved = true`, so webhook posts should not require approval. If they still do, ensure flarum/approval is enabled and the extension is up to date.

### Webhook returns 404 Not Found

- Double-check the discussion ID exists
- Ensure the discussion hasn't been deleted

## Development

This extension is built for Flarum 2.0 and follows modern Flarum development practices.

**File Structure:**
```
src/
├── Api/
│   └── Controller/
│       └── ReceiveWebhookController.php
└── Repository/
    └── ReleaseRepository.php
tests/
├── unit/
│   └── Repository/
│       └── ReleaseRepositoryTest.php
└── integration/
    └── Api/
        └── ReceiveWebhookTest.php
```

## Links

- [Packagist](https://packagist.org/packages/fof/extension-releases)
- [GitHub](https://github.com/fof/extension-releases)
- [Discuss](https://discuss.flarum.org/d/PUT_DISCUSS_SLUG_HERE)
- [Flarum 2.0 Documentation](https://docs.flarum.org/2.x/)

## License

MIT License. See [LICENSE.md](LICENSE.md) for details.

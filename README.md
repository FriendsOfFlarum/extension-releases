# FoF Extension Releases

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/extension-releases.svg)](https://packagist.org/packages/fof/extension-releases) [![Total Downloads](https://img.shields.io/packagist/dt/fof/extension-releases.svg)](https://packagist.org/packages/fof/extension-releases)

A [Flarum](https://flarum.org) 2.0 extension that automatically posts release notifications to discussions when you publish a new GitHub or GitLab release.

## Features

- 🚀 Automatic release notifications posted to Flarum discussions
- 🔐 Secure webhook endpoint with API token authentication
- 🎨 Customizable post template with translation support
- 👥 Username mapping from GitHub/GitLab to Flarum usernames for proper `@mentions`
- ✅ Full test coverage (unit and integration tests)
- 🔧 Support for both GitHub and GitLab webhooks

## Installation

Install with composer:

```sh
composer require fof/extension-releases
```

Then enable the extension in your Flarum admin panel.

## Configuration

### Step 1: Configure Extension Settings

1. Go to your Flarum admin panel
2. Navigate to **Extensions** → **FoF Extension Releases**
3. Configure **Username Mappings** (optional):
   - Maps GitHub/GitLab usernames to Flarum usernames
   - Format: JSON object, e.g., `{"github_user": "flarum_user", "another_github": "another_flarum"}`
   - This allows the extension to properly mention Flarum users when posting releases

Example username mapping:
```json
{
  "octocat": "john_doe",
  "jane-developer": "jane_smith"
}
```

### Step 2: Generate a Flarum API Token

You need an API token for the user who will post the release notifications:

**Prerequisites:**
- The user must have the **"Create access token"** permission (found in **Permissions** → **Moderate** section)
- The user should also have the **"Publish release updates via webhook"** permission (found in **Permissions** → **Start Discussions** section)

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

### Step 3: Setup GitHub Webhook

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
   - Add secret: `FLARUM_API_TOKEN` with your Flarum API token from Step 2

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

### Step 4: Setup GitLab Webhook

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
   - Add variable: `FLARUM_API_TOKEN` with your Flarum API token (mark as protected and masked)

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

**Request Body:**

```json
{
  "api_token": "string (required) - Flarum API token",
  "discussion_id": "integer (required) - Discussion ID to post to",
  "changelog": "string (required) - Release notes/changelog",
  "tag_name": "string (required) - Version tag (e.g., v1.0.0)",
  "release_url": "string (optional) - URL to the release page",
  "repository_name": "string (optional) - Repository name (e.g., owner/repo)",
  "author": "string (optional) - GitHub/GitLab username of release author"
}
```

**Responses:**

- `201 Created` - Post created successfully
  ```json
  {
    "success": true,
    "post_id": 123,
    "post_number": 5
  }
  ```

- `401 Unauthorized` - Invalid API token
  ```json
  {
    "error": "Invalid API token"
  }
  ```

- `403 Forbidden` - User lacks permission to reply
  ```json
  {
    "error": "User does not have permission to reply to this discussion"
  }
  ```

- `404 Not Found` - Discussion not found
  ```json
  {
    "error": "Discussion not found"
  }
  ```

- `422 Unprocessable Entity` - Missing required fields
  ```json
  {
    "error": "Missing required fields: api_token, discussion_id, changelog, tag_name"
  }
  ```

## Post Template

Release posts follow this template structure:

```
🚀 **Version {version}** has been released by @{author}!

## What's Changed

{changelog}

---

[**View full release →**]({release_url})

**Repository:** {repository_name}
```

You can customize the template by modifying the translation keys in `locale/en.yml` or providing translations in other languages.

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

### Webhook returns 404 Not Found

- Double-check the discussion ID exists
- Ensure the discussion hasn't been deleted

### Username mapping not working

- Verify the JSON format is correct in the extension settings
- Check for typos in usernames
- Remember: mapping keys are case-sensitive

## Development

This extension is built for Flarum 2.0 and follows modern Flarum development practices.

**File Structure:**
```
src/
├── Api/
│   └── Controller/
│       └── ReceiveWebhookController.php
├── Service/
│   ├── ReleaseNotificationService.php
│   └── ServiceProvider.php
tests/
├── unit/
│   └── Service/
│       └── ReleaseNotificationServiceTest.php
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

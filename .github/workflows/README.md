# GitHub Actions Workflows

This directory contains automated workflows for the Automotive Feed Import plugin.

## Available Workflows

### 1. Deploy to WordPress.org (`deploy-to-wordpress-org.yml`)

Automatically deploys the plugin to the WordPress.org plugin repository when a new release is published.

**Trigger:** Creating a new GitHub release

**What it does:**
- Checks out the repository code
- Prepares plugin files for deployment
- Deploys to WordPress.org SVN repository
- Creates a tagged release in SVN
- Generates and uploads a plugin ZIP file to the GitHub release

**Required Setup:**
See [DEPLOYMENT.md](../DEPLOYMENT.md) for complete setup instructions.

**Quick Start:**
1. Add `SVN_USERNAME` and `SVN_PASSWORD` as repository secrets
2. Create a new release on GitHub
3. Workflow runs automatically

## Adding New Workflows

To add new workflows:

1. Create a new `.yml` file in this directory
2. Define triggers (on push, pull request, schedule, etc.)
3. Add jobs and steps
4. Commit and push to enable

## Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [WordPress Plugin Deploy Action](https://github.com/10up/action-wordpress-plugin-deploy)
- [Main Deployment Guide](../DEPLOYMENT.md)

# Release Checklist

Use this checklist before creating each new release.

## Pre-Release Testing

- [ ] Test plugin activation/deactivation
- [ ] Test XML import functionality
- [ ] Test manual "Run Import Now" button
- [ ] Verify vehicle display on frontend (single page)
- [ ] Verify vehicle listing on archive page
- [ ] Test on different themes (Twenty Twenty-Four, etc.)
- [ ] Check responsive design (mobile, tablet, desktop)
- [ ] Test on different PHP versions (8.0, 8.1, 8.2)
- [ ] Check for PHP errors/warnings in debug mode
- [ ] Verify WordPress 6.0+ compatibility

## Version Updates

- [ ] Update version in `automotive-feed-import.php` header
  ```php
  Version: X.X
  ```
- [ ] Update stable tag in `readme.txt`
  ```
  Stable tag: X.X
  ```
- [ ] Verify versions match exactly
- [ ] Update `Tested up to` in readme.txt (current WP version)
- [ ] Update changelog in readme.txt with new features/fixes

## Code Quality

- [ ] Run PHP syntax check: `php -l automotive-feed-import.php`
- [ ] Check for PHP warnings with error_reporting(E_ALL)
- [ ] Verify proper escaping of all output
- [ ] Confirm nonce verification on all forms
- [ ] Check capability checks on admin functions
- [ ] Review security best practices

## Documentation

- [ ] Update README.md if needed
- [ ] Update screenshots if UI changed
- [ ] Write release notes (see template in DEPLOYMENT.md)
- [ ] Document any new features or changes
- [ ] Update FAQ section if needed

## Git & GitHub

- [ ] Commit all changes to main branch
- [ ] Push to GitHub: `git push origin main`
- [ ] Verify GitHub Actions workflow file exists
- [ ] Confirm secrets are set (SVN_USERNAME, SVN_PASSWORD)

## Create Release

- [ ] Go to GitHub repository → Releases
- [ ] Click "Create a new release"
- [ ] Create new tag: `X.X` (matches version number)
- [ ] Set release title: `Version X.X`
- [ ] Add detailed release notes
- [ ] Click "Publish release"

## Post-Release Verification

- [ ] Monitor GitHub Actions for successful deployment
- [ ] Check WordPress.org plugin page updates
- [ ] Verify download link works
- [ ] Test update process from previous version
- [ ] Check WordPress.org stats after 24 hours
- [ ] Monitor support forums for issues

## Version Number Format

**Current Version: 2.1**

**Next Version Options:**
- `2.2` - Minor update (new features, improvements)
- `2.1.1` - Patch (bug fixes only)
- `3.0` - Major update (breaking changes)

## Common Issues to Check

- [ ] No PHP errors in WordPress debug log
- [ ] No JavaScript console errors
- [ ] All settings save correctly
- [ ] Import log displays properly
- [ ] File browser works
- [ ] Cron schedule updates correctly
- [ ] Post meta saves properly
- [ ] Vehicle custom post type displays
- [ ] Permalinks work after activation
- [ ] No database errors

## After Successful Deployment

- [ ] Announce release (if applicable)
- [ ] Update any external documentation
- [ ] Monitor for user feedback
- [ ] Create GitHub milestone for next version (if planning features)

---

**Last Release:** 2.1
**Next Planned Release:** _____
**Target Date:** _____

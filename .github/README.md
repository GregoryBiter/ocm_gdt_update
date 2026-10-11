# Module release workflow template

Подробная документация на русском: [workflows/README.md](workflows/README.md).

Copy `workflows/build.yml`, `workflows/deploy.yml` and `scripts/package.py`
into another module repository. The repository root must contain `upload/`
and `opencart-module.json`.

Configure GitHub repository or `update-server` environment settings:

- Variable `UPDATE_SERVER_URL`: OpenCart catalog root, e.g. `https://example.com/`
  or `https://example.com/shop/index.php`.
- Secret `UPDATE_SERVER_API_KEY`: an OpenCart update-server key with publication
  permission, belonging to an active administrator allowed to modify the server.

Push a numeric version tag, e.g. `v1.2.3` or `1.2.3`. Build normalizes the
manifest version to `1.2.3` in the CI checkout, creates a ZIP including the
manifest and `upload/`, uploads an artifact and publishes a GitHub Release.
If the version differs, the workflow commits it to the default branch and
moves the new tag to that commit using an atomic push with explicit leases.
This requires a tag on the current default-branch HEAD and no existing release.
Release retries replace the
GitHub asset; OpenCart still rejects differing ZIP content for an existing version.

The build calls the reusable deploy workflow only after it succeeds. Deploy
normalizes and checks the manifest again, verifies the build artifact, and
posts that exact artifact to `index.php?route=gdt_update_server/api/upload`.
Code, name and author come from the manifest (`module_name`/`name` and
`creator_name`/`author`). HTTP errors and `success: false` fail deployment.

Reruns use Re-run jobs on the existing tag-triggered workflow run.
Environment protection rules may be configured on `update-server`.

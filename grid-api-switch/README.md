# GridPBX Kazoo client

Framework-independent PHP client used by `grid-api` to communicate with the
Kazoo Crossbar API.

The package owns:

- API-key authentication and token refresh behavior
- authenticated JSON transport
- normalized upstream exceptions
- typed resource clients and DTOs

It must not store tokens in files or expose Kazoo credentials to the browser.

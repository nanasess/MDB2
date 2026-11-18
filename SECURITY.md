# Security Policy

## Supported versions

Only the latest release receives security fixes. Please upgrade to it before reporting an issue.

## Reporting a vulnerability

Please do not open a public issue for a vulnerability.

Report it privately through [GitHub's private vulnerability reporting](https://github.com/nanasess/MDB2/security/advisories/new) instead. Include:

- the affected driver (`pgsql`, `mysqli`, `sqlite3`, ...), PHP version and database version
- the connection settings that matter (charset, SQL mode, options such as `lob_allow_url_include`)
- the steps or a short script that shows the problem

You will get a reply on the report. Once a fix is released, the advisory will be published with credit to the reporter unless you prefer otherwise.

Only the `pgsql`, `mysqli` and `sqlite3` drivers are tested. Reports for the other drivers are welcome, but they may not be fixed.

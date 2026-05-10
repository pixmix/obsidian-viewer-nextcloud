---
title: Query API
tags: [features, api, query, integration]
---

# Query API

The query API provides structured metadata extraction from vault files, similar to Obsidian's Dataview plugin but exposed as an HTTP endpoint suitable for external integration.

## Endpoint

```
POST /apps/obsidian_viewer/api/vaults/{vault}/query
Content-Type: application/json
```

### Request body

```json
{
  "fields": ["file.name", "title", "status", "tags"],
  "filter": "tag:project -tag:archived",
  "sort": "title",
  "limit": 50
}
```

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fields` | `string[]` | Yes | Properties to extract (see below) |
| `filter` | `string` | No | Search expression (same syntax as [[Search]]) |
| `sort` | `string` | No | Field name to sort by |
| `limit` | `number` | No | Max results (default 100, max 500) |

### Built-in fields

| Field | Value |
|-------|-------|
| `file.name` | File name without extension |
| `file.path` | Full vault-relative path |
| `file.folder` | Parent directory path |
| `file.size` | File size in bytes |
| `file.mtime` | Last modified timestamp (ISO 8601) |
| `file.links` | List of outgoing wiki-link targets |

Any other field name is looked up as a frontmatter property (e.g. `title`, `status`, `tags`, `created`).

### Response

```json
{
  "columns": ["file.name", "title", "status", "tags"],
  "rows": [
    ["Project Notes", "Project Notes", "active", ["project", "planning"]],
    ["Welcome", "Welcome", null, ["intro", "test"]]
  ]
}
```

## In-app query view

The app includes a visual query builder (toggled via the table icon in the action bar):

- **Field picker:** Click to add/remove columns
- **Filter input:** Free-text search expression with autocomplete hints
- **Sort dropdown:** Select a column to sort by
- **Results table:** Sticky headers, clickable file names for navigation

## External integration

### Nextcloud Analytics app

The query API is designed to work as an `ExternalJson` datasource for the Nextcloud Analytics app. Analytics requires exactly 3 columns (dimension 1, dimension 2, value), so structure your query accordingly:

```json
{
  "fields": ["file.name", "status", "file.size"],
  "filter": "tag:project"
}
```

The Analytics app can then poll this endpoint to build charts and dashboards from vault metadata.

### General integration pattern

Any tool that can make authenticated HTTP requests to Nextcloud can use the query API. The authentication model is the same as all Nextcloud APIs — session cookies, app passwords, or OAuth2.

```bash
# Example: list all files with a "status" property
curl -s -u USER:PASS -H 'OCS-APIREQUEST: true' \
  -H 'Content-Type: application/json' \
  -d '{"fields":["file.name","status","tags"],"filter":"[\"status\":]"}' \
  'https://your-server/apps/obsidian_viewer/api/vaults/MyVault/query'
```

## Design rationale

The query API exists because integrating Obsidian-style data access *within Nextcloud's ecosystem* matters more than replicating every Obsidian feature inside this app. Rather than building a full Dataview clone, the API exposes vault metadata in a standard format that other Nextcloud apps and external tools can consume. See [[Design Philosophy]] for more on this principle.

---
title: Search
tags: [features, search]
---

# Search

The app implements a full Obsidian-compatible search language, with identical parsers in both JavaScript (client-side, for graph filtering and groups) and PHP (server-side, for search results and query filtering).

## Search syntax

### Basic

| Syntax | Meaning |
|--------|---------|
| `foo bar` | Both "foo" AND "bar" must appear (implicit AND) |
| `foo OR bar` | Either term matches |
| `-foo` | Exclude notes containing "foo" |
| `"exact phrase"` | Match the exact phrase |
| `/regex/` | Match a regular expression |
| `(a OR b) c` | Grouping with parentheses |

### Operators

| Operator | Meaning |
|----------|---------|
| `file:name` | Match against file name/path |
| `path:folder` | Match against file path |
| `tag:name` | Match notes with a specific tag |
| `line:text` | Match a specific line |
| `section:text` | Match within a heading section |
| `block:text` | Match within a paragraph block |
| `task:text` | Match task list items |
| `task-todo:text` | Match unchecked tasks |
| `task-done:text` | Match checked tasks |
| `content:text` | Match in body content (excluding frontmatter) |

### Property queries

| Syntax | Meaning |
|--------|---------|
| `["title":value]` | Match a frontmatter property value |
| `["tags":test]` | Match notes with "test" in their tags property |

### Nested expressions

Operators accept full sub-expressions:

```
task:(call OR email)
tag:-test
line:(-debug -trace)
```

## Implementation

The parser is a recursive descent parser with three stages:

1. **Tokeniser:** Splits input into tokens (terms, operators, grouping, negation, quoted strings, regex)
2. **Parser:** Builds an AST from the token stream
3. **Evaluator:** Walks the AST against a note's content/metadata

The same logic exists in two implementations:
- **JavaScript:** `src/services/searchMatcher.js` — used for graph node filtering and group matching
- **PHP:** `VaultService.php` (`tokenizeQuery()`, `parseQuery()`, `evaluateNode()`) — used for the search API and query engine filtering

Both implementations are tested to produce identical results. Any change to the search language must be made in both files.

## Where search is used

- **Sidebar search tab:** Full-text vault search via the PHP backend
- **Graph filter:** Client-side filtering of graph nodes via JavaScript
- **Graph groups:** Client-side matching for colour assignment
- **Query API filter:** Server-side filtering in metadata queries (see [[Query API]])

# Znuny Process Documentation

A clean, browser-based process documentation tool for workflows and operational runbooks.

This project is intentionally generic: it does not depend on any specific Znuny installation internals. Instead, it works with a JSON data structure that can be imported once into the tool and then managed in the web UI.

## Features

- Process library overview
- Process editor
- Ticket field management
- Import and export of JSON documents
- Step-based process definition
- Variables and placeholder support
- Clean, maintainable PHP structure
- No direct dependency on a live Znuny instance

## Project structure

- `public/` – web entry point and frontend assets
- `src/` – domain logic and data access
- `data/` – JSON storage file
- `README.md` – usage notes

## Run locally

1. Make sure PHP is installed.
2. Start a local PHP web server:

```bash
cd public
php -S localhost:8000
```

3. Open:

```text
http://localhost:8000/
```

## JSON import format

The tool expects a JSON document like this:

```json
{
  "schemaVersion": 3,
  "configuration": {
    "fields": [
      {
        "id": "field-type",
        "key": "Type",
        "label": "Type",
        "type": "select",
        "values": ["Change", "Incident"],
        "defaultValue": "",
        "required": true
      }
    ],
    "linkTypes": ["Normal", "ParentChild"]
  },
  "processes": []
}
```

## Notes

This project is designed as a clean foundation for a process-documented workflow tool. It can be extended with richer templates, step conditions, imports from external systems, or YAML export without changing the core architecture.


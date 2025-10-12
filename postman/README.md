# Postman Collections

This directory contains example Postman collections for testing FlexiAPI endpoints.

## Usage

Generate a Postman collection for your endpoints:

```bash
flexiapi generate:postman
```

This will create a collection file that can be imported into Postman for testing your API endpoints.

## What's Included

The generated collection includes:
- All endpoint CRUD operations (GET, POST, PUT, DELETE)
- Authentication headers (Auth-x or X-API-Key)
- Example request bodies
- Environment variables for easy switching between dev/prod

## Import Instructions

1. Open Postman
2. Click "Import"
3. Select the generated `.postman_collection.json` file
4. Configure environment variables for your API URL and auth tokens
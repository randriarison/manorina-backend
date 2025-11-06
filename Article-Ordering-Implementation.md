# Article Ordering Implementation

## Summary
Updated the Article entity's GetCollection operations to order articles by `updatedAt DESC` and then by `descendantCount DESC`.

## Changes Made

### 1. Article Entity (`src/Entity/Article.php`)
Added `order` parameter to both GetCollection operations:

```php
// Main collection endpoint
new GetCollection(
    uriTemplate: '/articles',
    security: "true",
    normalizationContext: ['groups' => ['article:read']],
    order: ['updatedAt' => 'DESC', 'descendantCount' => 'DESC']
),

// Only-mine endpoint  
new GetCollection(
    uriTemplate: '/articles/only-mine',
    security: "is_granted('IS_AUTHENTICATED_REMEMBERED')",
    securityMessage: "You must be authenticated to access your articles.",
    normalizationContext: ['groups' => ['article:read', 'article:details']],
    provider: \App\State\UserArticlesProvider::class,
    order: ['updatedAt' => 'DESC', 'descendantCount' => 'DESC']
),
```

### 2. UserArticlesProvider (`src/State/UserArticlesProvider.php`)
Updated the ordering in the repository query:

```php
// Find articles authored by the current user
$articles = $this->articleRepository->findBy(
    ['author' => $user],
    ['updatedAt' => 'DESC', 'descendantCount' => 'DESC'], // Updated ordering
    $limit,
    $offset
);
```

## Ordering Logic

1. **Primary Sort**: `updatedAt DESC` - Most recently updated articles first
2. **Secondary Sort**: `descendantCount DESC` - Articles with more replies/children first

This ensures:
- Recently active content appears first
- Popular content (with more replies) is prioritized when update times are equal
- Consistent ordering across both main collection and user-specific endpoints

## API Endpoints Affected

- `GET /api/articles` - Main article collection
- `GET /api/articles/only-mine` - User's articles (requires authentication)

## Testing

Created `TestArticleOrderingCommand` to verify correct ordering:
```bash
php bin/console test:article-ordering
```

The test confirms articles are properly ordered by the specified criteria with ✅ status indicators for correct ordering.

## Verification

API responses now return articles in the following order:
1. Most recently updated articles first
2. For articles with identical update times, those with more descendants (replies/comments) appear first

This improves the user experience by surfacing the most active and engaging content at the top of the listings.
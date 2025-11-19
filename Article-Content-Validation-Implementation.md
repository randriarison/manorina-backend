# Article Content Validation Implementation

## Overview
Custom validation has been implemented for the Article entity to ensure proper content requirements based on article type and multilingual support (French/Malagasy).

## Validation Rules

### Rule A: Title Validation (Conditional)
**Applies to:** All article types EXCEPT `comment`, `answer`, and `paragraph`

**Requirements:** At least one of `title` or `titleMg` must:
- Not be null
- Not be empty string
- Not contain only spaces
- Contain more than 4 characters

**Exempt Article Types:**
- `COMMENT` - Comments don't require titles
- `ANSWER` - Answers don't require titles  
- `PARAGRAPH` - Paragraphs don't require titles

### Rule B: Content Validation (Always Applies)
**Applies to:** ALL article types

**Requirements:** At least one of `content` or `contentMg` must:
- Not be null
- Not be empty string
- Not contain only spaces
- Contain more than 4 characters

## Implementation Details

### Files Created

#### 1. `src/Validator/ArticleContentValidation.php`
Custom constraint class that defines the validation rules and error messages.

```php
#[\Attribute]
class ArticleContentValidation extends Constraint
{
    public string $titleRequiredMessage = 'For this article type, at least one title field (title or titleMg) must be provided and contain more than 4 characters.';
    public string $contentRequiredMessage = 'At least one content field (content or contentMg) must be provided and contain more than 4 characters.';
    
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
```

#### 2. `src/Validator/ArticleContentValidationValidator.php`
Validator class that implements the actual validation logic.

**Key Methods:**
- `shouldValidateTitle()` - Determines if title validation applies based on article type
- `validateTitleFields()` - Validates title and titleMg fields
- `validateContentFields()` - Validates content and contentMg fields
- `isFieldValid()` - Checks individual field requirements

#### 3. Updated `src/Entity/Article.php`
Added the validation constraint to the Article entity:

```php
#[ArticleContentValidation]
class Article
{
    // ... existing code
}
```

### Validation Logic Flow

1. **Type Check**: Determines if the article type requires title validation
2. **Title Validation**: If required, checks if at least one title field meets criteria
3. **Content Validation**: Always checks if at least one content field meets criteria
4. **Field Validation**: Each field is validated for:
   - Not null
   - Not empty after trimming whitespace
   - Length > 4 characters

## Testing

### Test Command
Created `TestArticleContentValidationCommand` to verify all validation scenarios:

```bash
php bin/console test:article-content-validation
```

### Test Cases Covered
1. ✅ Valid SIMPLE article with proper title and content
2. ✅ Invalid SIMPLE article with title too short
3. ✅ Invalid article with content too short
4. ✅ Valid COMMENT without title (content validation only)
5. ✅ Valid ANSWER without title (content validation only)
6. ✅ Valid PARAGRAPH without title (content validation only)
7. ✅ Valid article with only Malagasy content

### Test Results
```
Test Results
------------
Passed: 7/7

All tests passed! Article content validation is working correctly.
```

## Multilingual Support

The validation properly supports both French and Malagasy content:

### Example Valid Scenarios
```php
// Valid: French only
$article->setTitle('Article Title');
$article->setContent('Article content here');

// Valid: Malagasy only  
$article->setTitleMg('Lohateny marina');
$article->setContentMg('Votoatin-kevitra marina eto');

// Valid: Mixed languages
$article->setTitle('French Title');
$article->setContentMg('Votoatin-kevitra Malagasy');
```

## Integration with Existing Validators

The new validation works alongside existing validators:
- `CommentMustHaveParent` - Still enforced for comments
- `AnswerMustHaveQuestionParent` - Still enforced for answers
- Content validation is independent and always applies

## API Integration

### Validation Errors in API Responses
When validation fails, the API will return detailed error messages:

```json
{
    "violations": [
        {
            "propertyPath": "title",
            "message": "For this article type, at least one title field (title or titleMg) must be provided and contain more than 4 characters."
        },
        {
            "propertyPath": "content", 
            "message": "At least one content field (content or contentMg) must be provided and contain more than 4 characters."
        }
    ]
}
```

### Article Types and Title Requirements

| Article Type | Title Required | Notes |
|--------------|----------------|-------|
| `SIMPLE` | ✅ Yes | Standard articles need titles |
| `QUESTION` | ✅ Yes | Questions need descriptive titles |
| `CHANGE_SUGGESTION` | ✅ Yes | Suggestions need clear titles |
| `COMMENT` | ❌ No | Comments don't need titles |
| `ANSWER` | ❌ No | Answers respond to questions |
| `PARAGRAPH` | ❌ No | Paragraphs are content blocks |

## Benefits

1. **Data Quality**: Ensures meaningful content in all articles
2. **Multilingual Support**: Validates either French or Malagasy content
3. **Type-Aware**: Different rules for different article types
4. **User-Friendly**: Clear error messages guide users
5. **Flexible**: Easy to modify rules or add new article types
6. **Comprehensive**: Covers all edge cases (null, empty, whitespace-only)

## Future Enhancements

Potential improvements could include:
- Configurable minimum character limits
- HTML tag stripping for content validation
- Rich text content validation
- Custom validation rules per article category
- Language detection and validation

The validation system is designed to be extensible and maintainable while providing robust content quality assurance.
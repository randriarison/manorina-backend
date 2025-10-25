<?php

namespace App\Enum;

enum ArticleType: string
{
    case SIMPLE = 'simple';
    case QUESTION = 'question';
    case ANSWER = 'answer';
    case PARAGRAPH = 'paragraph';
    case COMMENT = 'comment';

    public function label(): string
    {
        return match ($this) {
            self::SIMPLE => 'Simple',
            self::QUESTION => 'Question',
            self::ANSWER => 'Response',
            self::PARAGRAPH => 'Paragraph',
            self::COMMENT => 'Comment',
        };
    }
}

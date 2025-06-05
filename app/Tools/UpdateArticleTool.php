<?php

namespace App\Tools;

use Prism\Prism\Tool;
use App\Models\Article;

class UpdateArticleTool extends Tool
{
    public function __construct(private Article $article)
    {
        $this
            ->as('update_article')
            ->for('Update the article outline or content')
            ->withStringParameter('outline', 'New outline for the article')
            ->withStringParameter('content', 'New content for the article')
            ->using($this);
    }

    public function __invoke(string $outline = null, string $content = null): string
    {
        if ($outline !== null) {
            $this->article->outline = $outline;
        }
        if ($content !== null) {
            $this->article->content = $content;
        }
        $this->article->save();

        return 'Article updated';
    }
}

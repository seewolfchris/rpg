<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class PostContentRenderResult
{
    /**
     * @param  list<int>  $inlineMediaIds
     */
    public function __construct(
        private readonly HtmlString $html,
        private readonly array $inlineMediaIds = [],
    ) {}

    public function html(): HtmlString
    {
        return $this->html;
    }

    public function toHtml(): string
    {
        return $this->html->toHtml();
    }

    /**
     * @return list<int>
     */
    public function inlineMediaIds(): array
    {
        return $this->inlineMediaIds;
    }
}

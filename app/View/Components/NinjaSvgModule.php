<?php

namespace App\View\Components;

use Closure;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Support\Htmlable;

class NinjaSvgModule extends Component implements Htmlable
{
    private string $innerSvg;

    private string $contents;

    public function __construct(
        public string $inscriptionId
    ) {
        $this->readContentsFromDisk();
    }

    public function readContentsFromDisk(): void
    {
        $svgContent = Storage::disk('ninja_modules')->get($this->inscriptionId.'.svg');

        $this->innerSvg = Str::of($svgContent)
            ->replaceMatches('/<svg[^>]*>/', '')
            ->replaceMatches('/<\/svg>/', '')
            ->__toString();

        $this->contents = Str::of($this->openTag())
            ->append($this->innerSvg)
            ->append($this->closeTag());
    }


    public function openTag(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">';
    }

    public function closeTag(): string
    {
        return '</svg>';
    }

    public function toHtml(): string
    {
        return $this->contents;
    }

    public function render(): string
    {
        return $this->toHtml();
    }
}

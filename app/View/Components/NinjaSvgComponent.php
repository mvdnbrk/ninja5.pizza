<?php

namespace App\View\Components;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\Component;

class NinjaSvgComponent extends Component implements Htmlable
{
    private string $innerSvgContent = '';

    private string $styleElement = '';

    private string $fileContent = '';

    private Collection $cssRules;

    private Collection $placeholders;

    public function __construct(
        public string $inscriptionId,
        public array $config,
    ) {
        $this->readContentsFromDisk();

        $this->extractInnerSvgContent();

        $this->extractStyleElement();

        $this->removeNewlinesFromSvgPaths();

        $this->parseCssRulesToCollection();

        $this->parsePlaceholdersToCollection();

        $this->addCssStringsToInnerSvg();

        $this->replacePlaceholders();

        $this->removeClassAttributesFromInnerSvg();
    }

    protected function extractStyleElement(): void
    {
        $this->innerSvgContent = Str::of($this->innerSvgContent)
            ->replaceMatches(
                pattern: '/<style\s+type="text\/css">(.*?)<\/style>/si',
                replace: function ($match) {
                    $this->styleElement = $match[0];

                    return '';
                }
            )
            ->trim()
            ->toString();

        $this->removeDeprecatedCssAttributes();
    }

    protected function extractInnerSvgContent(): void
    {
        $this->innerSvgContent = Str::of($this->fileContent)
            ->replaceMatches(
                pattern: '/<svg[^>]*>/',
                replace: '',
            )
            ->replaceMatches(
                pattern: '/<\/svg>/',
                replace: '',
            )
            ->toString();
    }

    protected function parseCssRulesToAttributeString(): Collection
    {
        return $this->cssRules->map(function ($attributes) {
            return collect($attributes)->map(function ($value, $key) {
                return "$key=\"$value\"";
            })->implode(' ');
        });
    }

    protected function addCssStringsToInnerSvg(): void
    {
        $this->parseCssRulesToAttributeString()->each(function ($string, $class) {
            $pattern = "/class=\"$class\"([^>]*)/";

            $this->innerSvgContent = Str::replaceMatches(
                pattern: $pattern,
                replace: function ($match) use ($class, $string) {
                    return 'class="'.$class.'" '.$string.$match[1];
                },
                subject: $this->innerSvgContent,
            );
        });
    }

    protected function parseCssRulesToCollection(): void
    {
        $cssContent = Str::of($this->styleElement())
            ->between('<style type="text/css">', '</style>')
            ->trim();

        $this->cssRules = Str::of($cssContent)->split('/\}/')
            ->map(function ($rule) {
                $rule = Str::of($rule)->trim();

                if ($rule->isEmpty()) {
                    return null;
                }

                $ruleParts = $rule->split('/\\{/', 2);

                $selector = ltrim($ruleParts[0], '.');
                $properties = $ruleParts[1];

                $properties = Str::of($properties)->split('/\s*;\s*/')
                    ->map(function ($property) {
                        if (Str::of($property)->trim()->isEmpty()) {
                            return null;
                        }

                        $parts = Str::of($property)->split('/:/', 2);

                        if ($parts->count() == 2) {
                            return [$parts[0] => $parts[1]];
                        }

                        return null;
                    })
                    ->filter()
                    ->collapse()
                    ->toArray();

                return [
                    $selector => $properties,
                ];
            })
            ->collapse();
    }

    protected function parsePlaceholdersToCollection(): void
    {
        $this->placeholders = Collection::make($this->config)
            ->filter(function ($value, $key) {
                return preg_match('/^ST\d+$/', $key);
            })
            ->mapWithKeys(function ($value, $key) {
                return ['%%'.$key.'%%' => $value];
            });
    }

    protected function removeDeprecatedCssAttributes(): void
    {
        $this->styleElement = Str::of($this->styleElement)
            ->replaceMatches(
                pattern: '/enable-background:\s*new\s*\;/',
                replace: '',
            )
            ->toString();
    }

    protected function removeNewlinesFromSvgPaths(): void
    {
        $this->innerSvgContent = Str::of($this->innerSvgContent)
            ->replaceMatches(
                pattern: '/<path[^>]*\/>/s',
                replace: function ($matches) {
                    return str_replace(["\r\n", "\n", "\r"], ' ', $matches[0]);
                }
            )
            ->toString();
    }

    protected function removeClassAttributesFromInnerSvg(): void
    {
        $this->innerSvgContent = Str::replaceMatches(
            pattern: "/ class=\"st\d+\"/i",
            replace: fn () => '',
            subject: $this->innerSvgContent
        );
    }

    protected function replacePlaceholders(): void
    {
        $this->placeholders->each(function ($value, $placeholder) {
            $this->innerSvgContent = Str::replaceMatches(
                pattern: '/'.preg_quote($placeholder, '/').'/',
                replace: fn () => $value,
                subject: $this->innerSvgContent
            );
        });
    }

    protected function readContentsFromDisk(): void
    {
        $this->fileContent = Storage::disk('ninja_components')
            ->get($this->inscriptionId.'.svg') ?? '';

        $this->fileContent = Str::of($this->fileContent)
            ->replace("\t", '')
            ->trim()
            ->toString();
    }

    public function openTag(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000">'.PHP_EOL;
    }

    public function closeTag(): string
    {
        return PHP_EOL.'</svg>';
    }

    public function backgroundRectangle(): string
    {
        return '<rect x="0" y="0" width="1000" height="1000" fill="#FF5400"/>'.PHP_EOL;
    }

    public function innerSvgContent(): string
    {
        return $this->innerSvgContent;
    }

    public function styleElement(): string
    {
        return $this->styleElement;
    }

    public function toHtml(): string
    {
        return Str::of($this->openTag())
            ->append($this->innerSvgContent)
            ->append($this->closeTag())
            ->toString();
    }

    public function render(): string
    {
        return $this->toHtml();
    }
}

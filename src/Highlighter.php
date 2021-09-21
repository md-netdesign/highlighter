<?php

namespace MdNetdesign\Highlighter;

use RuntimeException;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Highlighter extends AbstractExtension
{

  public function getFilters(): iterable {
    yield new TwigFilter("highlight", [$this, "highlight"], ["needs_environment" => true, "is_safe" => ["html"]]);
  }

  public function highlight(Environment $environment, $value, ?string $search): string {
    list($value, $lowercaseValue) = $this->getEscapedValue($environment, $value);

    if ($search === null || !is_string($value)) // return the value, if no search is provided
      return $value ?? "";

    $words = $this->getEscapedWords($environment, $search);

    $characters = $this->findHighlightedCharacters($lowercaseValue, $words);

    return $this->createHighlightMarkup($value, $characters);
  }

  private function getEscapedValue(Environment $environment, ?string $value): array {
    if ($value === null)
      return [null, null];

    try {
      // try to escape the given value, so no arbitrary html is inside
      $value = twig_escape_filter($environment, $value);
      $lowercaseValue = mb_strtolower($value);

    } catch (RuntimeError $e) {
      throw new RuntimeException("Could not escape input.", 0, $e);
    }

    return [$value, $lowercaseValue];
  }

  private function getEscapedWords(Environment $environment, string $query): array {
    try {
      // do this also with every word of the search request
      $words = [];
      foreach (explode(" ", $query) as $word)
        if (!empty($word = trim($word)))
          $words[] = twig_escape_filter($environment, mb_strtolower($word));

    } catch (RuntimeError $e) {
      throw new RuntimeException("Could not escape input.", 0, $e);
    }

    return $words;
  }

  private function findHighlightedCharacters(string $lowercaseValue, array $words): array {
    $characters = [];

    foreach ($words as $word) {
      if (($position = mb_strpos($lowercaseValue, $word)) === false)
        continue;

      // index all character positions of highlighted words
      do $characters = array_merge($characters, range($position, $position + mb_strlen($word) - 1));
      while (($position = mb_strpos($lowercaseValue, $word, $position + 1)) !== false);
    }

    return $characters;
  }

  private function createHighlightMarkup(string $value, array $characters): string {
    // create the highlight string with <mark> tags
    $tagOpened = false;
    $highlightedValue = "";
    for ($i = 0, $s = mb_strlen($value); $i < $s; $i++) {
      if (($toHighlight = in_array($i, $characters)) && !$tagOpened) {
        $highlightedValue .= "<mark>";
        $tagOpened = true;
      }

      if (!$toHighlight && $tagOpened) {
        $highlightedValue .= "</mark>";
        $tagOpened = false;
      }

      $highlightedValue .= mb_substr($value, $i, 1);
    }

    if ($tagOpened)
      $highlightedValue .= "</mark>";

    return $highlightedValue;
  }

}
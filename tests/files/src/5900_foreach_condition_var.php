<?php

class ForeachConditionVar
{
    /** @return array<int,array{content:mixed}> */
    private function getTranslationsOf(): array
    {
        return [];
    }

    public function check(mixed $content): void
    {
        $translations = $this->getTranslationsOf();
        $needsWarning = false;
        if (is_array($content)) {
            foreach ($translations as $translation) {
                if (!isset($translation['content'])) {
                    continue;
                }
                $needsWarning = !is_array($translation['content']);
                if ($needsWarning) {
                    break;
                }
            }
        }

        if ($needsWarning) {
            echo 'needs warning';
        }
    }
}

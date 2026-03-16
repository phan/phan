<?php

// Test that track_all_inferred_types causes phound to detect callsites
// on concrete types assigned to interface-typed properties.

namespace PhoundTrackAllInferredTypes;

interface Renderable {
    public function render(): string;
}

class HtmlRenderer implements Renderable {
    public function render(): string {
        return '<html>';
    }
    public function minify(): string {
        return '<html/>';
    }
}

class JsonRenderer implements Renderable {
    public function render(): string {
        return '{}';
    }
    public function prettify(): string {
        return '{ }';
    }
}

/* @phan-suppress-next-line PhanUnreferencedClass */
class Page {
    private Renderable $renderer;

    public function __construct() {
        $this->renderer = new HtmlRenderer();
    }

    public function switchRenderer(): void {
        $this->renderer = new JsonRenderer();
    }

    // Return type widening: declared return type is Renderable, but Phan
    // infers the concrete HtmlRenderer type and widens the return type.
    public function getRenderer(): Renderable {
        return new HtmlRenderer();
    }

    /* @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function output(): string {
        // With track_all_inferred_types, phound should detect callsites for
        // HtmlRenderer::render and JsonRenderer::render here,
        // in addition to Renderable::render.
        $result = $this->renderer->render();

        // Return type widening: getRenderer() is declared as returning Renderable,
        // but with track_all_inferred_types, phound should also detect
        // HtmlRenderer::render here.
        $result .= $this->getRenderer()->render();

        return $result;
    }
}

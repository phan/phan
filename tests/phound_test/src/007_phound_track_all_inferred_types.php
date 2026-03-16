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

    /* @phan-suppress-next-line PhanUnreferencedPublicMethod */
    public function output(): string {
        // With track_all_inferred_types, phound should detect callsites for
        // HtmlRenderer::render and JsonRenderer::render here,
        // in addition to Renderable::render.
        return $this->renderer->render();
    }
}

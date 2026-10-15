<?php

// Only overrides; Livewire merges the rest of its defaults.
return [

    // Livewire 4 defaults to 'layouts::app' (resources/views/layouts/app.blade.php),
    // which this app does not have. Volt pages without an explicit layout (the
    // settings pages) use the existing component layout instead.
    'component_layout' => 'components.layouts.app',

];

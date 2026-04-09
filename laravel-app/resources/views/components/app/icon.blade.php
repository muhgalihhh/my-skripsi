@props([
    'name' => null,
    'variant' => null,
])

@php
    $classAttr = (string) $attributes->get('class', '');

    $resolvedName = $name;
    if ($resolvedName === null) {
        if (str_contains($classAttr, 'animate-spin')) {
            $resolvedName = 'arrow-path';
        } elseif (str_contains($classAttr, 'text-red')) {
            $resolvedName = 'x-circle';
        } elseif (str_contains($classAttr, 'text-green') || str_contains($classAttr, 'text-emerald')) {
            $resolvedName = 'check-circle';
        } elseif (str_contains($classAttr, 'text-amber') || str_contains($classAttr, 'text-yellow')) {
            $resolvedName = 'exclamation-triangle';
        } elseif (str_contains($classAttr, 'text-blue') || str_contains($classAttr, 'text-cyan') || str_contains($classAttr, 'text-unsoed-blue')) {
            $resolvedName = 'information-circle';
        } else {
            $resolvedName = 'sparkles';
        }
    }

    $resolvedVariant = $variant;
    if ($resolvedVariant === null) {
        $resolvedVariant = str_contains($classAttr, 'text-white') ? 's' : 'o';
    }

    $normalizedVariant = in_array($resolvedVariant, ['o', 's', 'm'], true) ? $resolvedVariant : 'o';
    $componentName = 'heroicon-' . $normalizedVariant . '-' . $resolvedName;
@endphp

<x-dynamic-component :component="$componentName" {{ $attributes }} />

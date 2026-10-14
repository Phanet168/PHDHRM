@props(['title' => null, 'metaDescription' => null, 'ogImage' => null, 'canonical' => null])
@include('layouts.app', ['title' => $title, 'metaDescription' => $metaDescription, 'ogImage' => $ogImage, 'canonical' => $canonical, 'slot' => $slot])

<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('store.pages.about');
    }

    public function shipping(): View
    {
        return view('store.pages.shipping');
    }

    public function returns(): View
    {
        return view('store.pages.returns');
    }

    public function contact(): View
    {
        return view('store.pages.contact');
    }

    public function faq(): View
    {
        return view('store.pages.faq');
    }

    public function privacy(): View
    {
        return view('store.pages.privacy');
    }
}

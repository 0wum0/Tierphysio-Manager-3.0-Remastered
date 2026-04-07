<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;

class DemoController extends Controller
{
    public function __construct(View $view, Session $session)
    {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->render('demo/index.twig', [
            'page_title' => 'Demo – TheraPano Praxis-Software',
        ]);
    }
}

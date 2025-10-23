<?php

namespace Gzai\LaravelBoxAdapter\Http\Controllers;

use Illuminate\Routing\Controller;
use Gzai\LaravelBoxAdapter\Services\BoxAdapterService;

class BoxUserController extends Controller
{
	public function me(BoxAdapterService $box)
	{
		if ( $box->getAccessToken() ) {
            return $box->getUser();
        }
	}
}
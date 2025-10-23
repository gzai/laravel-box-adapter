<?php

namespace Gzai\LaravelBoxAdapter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Gzai\LaravelBoxAdapter\Services\BoxAdapterService;

use Redirect;

class BoxAuthController extends Controller
{
	public function login(BoxAdapterService $box)
	{
		return redirect($box->getAuthorizationUrl());
	}

	public function callback(Request $request, BoxAdapterService $box)
	{
		$code = $request->query('code');

        if ( $code != '' ) {
        	if ( config('box.redirect_callback') ) {
        		return Redirect::to( config('box.redirect_callback_url') );
        	} else {
            	return $box->getTokenFromCode($code);
        	}
        }

        return [
            'success' => false,
            'error' => [
	            'code' => 'unknown_error',
	            'message' => 'Parameter code is required',
	            'status' => '400',
            ]
        ];
	}
}
<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request; 
use STS\Http\Controllers\Controller;
use STS\Services\Logic\ReferencesManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException; 

class ReferencesController extends Controller
{
    protected $referencesLogic;

    protected $user;

    public function __construct(ReferencesManager $referencesLogic)
    {
        $this->middleware('logged');
        $this->referencesLogic = $referencesLogic;
    }

    public function create(Request $request)
    {
        $this->user = auth()->user();

        if ($this->user) {
            $data = $request->all();

            $reference = $this->referencesLogic->create($this->user, $data);
            if ($reference) {
                return response()->json($reference);
            } else {
                throw new BadRequestHttpException('Could not rate user.', $this->referencesLogic->getErrors());
            }
        } else {
            throw new BadRequestHttpException('User not logged.');
        }
    }
}

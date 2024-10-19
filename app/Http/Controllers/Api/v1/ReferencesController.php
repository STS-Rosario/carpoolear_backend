<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request; 
use STS\Http\Controllers\Controller;
use STS\Http\ExceptionWithErrors;
use STS\Services\Logic\ReferencesManager;

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
                throw new ExceptionWithErrors('Could not rate user.', $this->referencesLogic->getErrors());
            }
        } else {
            throw new ExceptionWithErrors('User not logged.');
        }
    }
}

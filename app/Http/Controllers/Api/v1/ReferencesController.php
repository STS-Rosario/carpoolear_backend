<?php

namespace STS\Http\Controllers\Api\v1;

use Illuminate\Http\Request;
use STS\Contracts\Logic\IReferencesLogic;
use STS\Http\Controllers\Controller;
use Dingo\Api\Exception\BadRequestHttpException;
use Dingo\Api\Exception\UpdateResourceFailedException;

class ReferencesController extends Controller
{
    protected $referencesLogic;

    protected $user;

    public function __construct(IReferencesLogic $referencesLogic)
    {
        $this->middleware('logged');
        $this->referencesLogic = $referencesLogic;
    }

    public function create(Request $request)
    {
        $this->user = $this->auth->user();

        if ($this->user) {
            $data = $request->all();

            $reference = $this->referencesLogic->create($this->user, $data);
            if ($reference) {
                return response()->json($reference);
            } else {
                throw new UpdateResourceFailedException('Could not rate user.', $this->referencesLogic->getErrors());
            }
        } else {
            throw new BadRequestHttpException('User not logged.');
        }
    }
}

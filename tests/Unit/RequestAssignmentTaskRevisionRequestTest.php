<?php

namespace Tests\Unit;

use App\Domains\Supervisor\Requests\RequestAssignmentTaskRevisionRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class RequestAssignmentTaskRevisionRequestTest extends TestCase
{
    public function test_it_accepts_postman_style_key_value_payloads(): void
    {
        $request = RequestAssignmentTaskRevisionRequest::create('/request-revision', 'PATCH', [
            [
                'key' => 'feedback',
                'value' => 'goodgoodgoodgoodgoodgood',
                'enabled' => true,
            ],
        ]);

        $this->invokePrepareForValidation($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertSame('goodgoodgoodgoodgoodgood', $request->input('feedback'));
        $this->assertFalse($validator->fails());
    }

    private function invokePrepareForValidation(RequestAssignmentTaskRevisionRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}

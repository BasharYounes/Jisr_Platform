<?php

namespace Tests\Unit;

use App\Domains\Student\Requests\SubmitAssignmentTaskRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class SubmitAssignmentTaskRequestTest extends TestCase
{
    public function test_it_accepts_submission_url_aliases(): void
    {
        $request = SubmitAssignmentTaskRequest::create('/submit', 'PATCH', [
            'submissionUrl' => 'https://github.com/BasharYounes/Jisr_Platform',
        ]);

        $this->invokePrepareForValidation($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertSame('https://github.com/BasharYounes/Jisr_Platform', $request->input('submission_url'));
        $this->assertFalse($validator->fails());
    }

    public function test_it_accepts_github_branch_aliases(): void
    {
        $request = SubmitAssignmentTaskRequest::create('/submit', 'PATCH', [
            'githubBranchOrLink' => 'bashar',
        ]);

        $this->invokePrepareForValidation($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertSame('bashar', $request->input('github_branch_or_link'));
        $this->assertFalse($validator->fails());
    }

    public function test_it_accepts_nested_data_payloads(): void
    {
        $request = SubmitAssignmentTaskRequest::create('/submit', 'PATCH', [
            'data' => [
                'submission_url' => 'https://github.com/BasharYounes/Jisr_Platform',
                'github_branch_or_link' => 'bashar',
            ],
        ]);

        $this->invokePrepareForValidation($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertSame('https://github.com/BasharYounes/Jisr_Platform', $request->input('submission_url'));
        $this->assertSame('bashar', $request->input('github_branch_or_link'));
        $this->assertFalse($validator->fails());
    }

    public function test_it_accepts_postman_style_key_value_payloads(): void
    {
        $request = SubmitAssignmentTaskRequest::create('/submit', 'PATCH', [
            [
                'key' => 'submission_url',
                'value' => 'https://github.com/BasharYounes/Jisr_Platform',
                'enabled' => true,
            ],
            [
                'key' => 'github_branch_or_link',
                'value' => 'bashar',
                'enabled' => true,
            ],
        ]);

        $this->invokePrepareForValidation($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertSame('https://github.com/BasharYounes/Jisr_Platform', $request->input('submission_url'));
        $this->assertSame('bashar', $request->input('github_branch_or_link'));
        $this->assertFalse($validator->fails());
    }

    private function invokePrepareForValidation(SubmitAssignmentTaskRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}

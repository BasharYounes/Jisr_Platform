<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class FlaskExpertRulesSeeder extends Seeder
{
    use ResolvesExpertQuestionsByTopic;

    private const SKILL_NAME = 'Flask';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->guardNoExistingAttempts();

            $now = now();

            foreach ($this->definitions() as $definition) {
                $this->syncQuestion(
                    definition: $definition,
                    now: $now,
                );
            }
        });

        if ($this->command) {
            $this->command->info(
                'Flask Expert Rules data was seeded successfully.'
            );
        }
    }

    private function guardNoExistingAttempts(): void
    {
        $questionIds = $this->expertQuestionIdsByTopics(
            skillName: self::SKILL_NAME,
            topics: array_column($this->definitions(), 'topic'),
        );

        $attemptCount = DB::table('assessment_question_attempts')
            ->whereIn('QuestionID', $questionIds)
            ->count();

        if ($attemptCount > 0) {
            throw new RuntimeException(
                'Cannot seed Flask questions because one or more '
                .'questions already have assessment attempts. '
                .'Create a new Rule Set version instead of replacing rules.'
            );
        }
    }

    private function syncQuestion(
        array $definition,
        $now,
    ): void {
        $question = $this->resolveExpertQuestionByTopic(
            skillName: self::SKILL_NAME,
            topic: $definition['topic'],
        );

        $questionId = (int) $question->QuestionID;

        $this->clearQuestionStructure($questionId);

        DB::table('question_bank')
            ->where('QuestionID', $questionId)
            ->update([
                'QuestionText' => $definition['question_text'],
                'Topic' => $definition['topic'],
                'EvaluationEngine' => 'expert_rules',
                'RuleSetVersion' => 'v1',
                'IsExpertReady' => false,
                'updated_at' => $now,
            ]);

        $conceptIds = [];

        foreach ($definition['concepts'] as $concept) {
            $conceptIds[$concept['code']] = $this->ensureConcept(
                concept: $concept,
                now: $now,
            );
        }

        $rubricIds = [];

        foreach ($definition['rubrics'] as $rubric) {
            $rubricIds[$rubric['code']] = DB::table('question_rubrics')
                ->insertGetId([
                    'QuestionID' => $questionId,
                    'CriterionCode' => $rubric['code'],
                    'CriterionName' => $rubric['name_ar'],
                    'CriterionDescription' => $rubric['description_ar'],
                    'MaxScore' => $rubric['max_score'],
                    'Weight' => 1.00,
                    'KeywordsJson' => $this->json(
                        $rubric['requires']
                    ),
                    'SampleGoodAnswer' => $rubric['sample_good'],
                    'SampleBadAnswer' => $rubric['sample_bad'],
                    'FeedbackOnPass' => $rubric['feedback_pass'],
                    'FeedbackOnFail' => $rubric['feedback_fail'],
                    'OrderIndex' => $rubric['order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], 'QuestionRubricID');
        }

        $ruleSetId = DB::table('assessment_rule_sets')
            ->insertGetId([
                'QuestionID' => $questionId,
                'RuleSetCode' => $definition['rule_set_code'],
                'Version' => 'v1',
                'Status' => 'active',
                'CreatedByUserId' => null,
                'ActivatedAt' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'RuleSetID');

        foreach ($definition['rubrics'] as $rubric) {
            DB::table('criterion_rules')->insert([
                'RuleSetID' => $ruleSetId,
                'QuestionRubricID' => $rubricIds[$rubric['code']],
                'RuleCode' => $rubric['code'].'_FULL',
                'RuleType' => 'award_full',
                'Priority' => 10,
                'ConditionsJson' => $this->json([
                    'all' => array_map(
                        fn (string $conceptCode): array => [
                            'concept' => $conceptCode,
                            'expected' => true,
                            'not_negated' => true,
                        ],
                        $rubric['requires']
                    ),
                    'none' => array_map(
                        fn (string $conceptCode): array => [
                            'concept' => $conceptCode,
                            'expected' => true,
                        ],
                        $rubric['blocked_by']
                    ),
                ]),
                'AwardScore' => $rubric['max_score'],
                'FeedbackTemplate' => $rubric['feedback_pass'],
                'IsActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($definition['contradictions'] as $contradiction) {
            $triggerConcept = $contradiction['trigger_concept'];

            if (! isset($conceptIds[$triggerConcept])) {
                throw new RuntimeException(
                    "Unknown contradiction concept: {$triggerConcept}"
                );
            }

            $contradictionRuleId = DB::table(
                'assessment_contradiction_rules'
            )->insertGetId([
                'RuleSetID' => $ruleSetId,
                'TriggerConceptID' => $conceptIds[$triggerConcept],
                'Code' => $contradiction['code'],
                'Severity' => 'block_criterion',
                'FeedbackAr' => $contradiction['feedback_ar'],
                'IsActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'ContradictionRuleID');

            foreach ($contradiction['blocked_rubrics'] as $rubricCode) {
                if (! isset($rubricIds[$rubricCode])) {
                    throw new RuntimeException(
                        "Unknown rubric code: {$rubricCode}"
                    );
                }

                DB::table(
                    'assessment_contradiction_rule_rubrics'
                )->insert([
                    'ContradictionRuleID' => $contradictionRuleId,
                    'QuestionRubricID' => $rubricIds[$rubricCode],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function clearQuestionStructure(int $questionId): void
    {
        $ruleSetIds = DB::table('assessment_rule_sets')
            ->where('QuestionID', $questionId)
            ->pluck('RuleSetID')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! empty($ruleSetIds)) {
            $contradictionRuleIds = DB::table(
                'assessment_contradiction_rules'
            )
                ->whereIn('RuleSetID', $ruleSetIds)
                ->pluck('ContradictionRuleID')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! empty($contradictionRuleIds)) {
                DB::table('assessment_contradiction_rule_rubrics')
                    ->whereIn(
                        'ContradictionRuleID',
                        $contradictionRuleIds
                    )
                    ->delete();

                DB::table('assessment_contradiction_rules')
                    ->whereIn(
                        'ContradictionRuleID',
                        $contradictionRuleIds
                    )
                    ->delete();
            }

            DB::table('criterion_rules')
                ->whereIn('RuleSetID', $ruleSetIds)
                ->delete();

            DB::table('assessment_rule_sets')
                ->whereIn('RuleSetID', $ruleSetIds)
                ->delete();
        }

        DB::table('question_rubrics')
            ->where('QuestionID', $questionId)
            ->delete();
    }

    private function ensureConcept(
        array $concept,
        $now,
    ): int {
        $existingId = DB::table('assessment_concepts')
            ->where('ConceptCode', $concept['code'])
            ->value('ConceptID');

        if ($existingId) {
            DB::table('assessment_concepts')
                ->where('ConceptID', $existingId)
                ->update([
                    'NameAr' => $concept['name_ar'],
                    'NameEn' => $concept['name_en'],
                    'Description' => $concept['description_ar'],
                    'ClaimAr' => $concept['claim_ar'],
                    'ClaimEn' => $concept['claim_en'],
                    'IsActive' => true,
                    'updated_at' => $now,
                ]);

            return (int) $existingId;
        }

        return (int) DB::table('assessment_concepts')
            ->insertGetId([
                'ConceptCode' => $concept['code'],
                'NameAr' => $concept['name_ar'],
                'NameEn' => $concept['name_en'],
                'Description' => $concept['description_ar'],
                'ClaimAr' => $concept['claim_ar'],
                'ClaimEn' => $concept['claim_en'],
                'IsActive' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], 'ConceptID');
    }

    private function json(array $data): string
    {
        try {
            return json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new LogicException(
                'Unable to encode Expert Rules JSON.',
                previous: $exception
            );
        }
    }

    private function definitions(): array
    {
        return
[
    [
        'question_text' => 'كيف تنشئ تطبيق Flask بسيط يعرض "Hello World"؟',
        'topic' => 'flask_hello_world',
        'rule_set_code' => 'FLASK_HELLO_WORLD_V1',
        'concepts' => [
            [
                'code' => 'flask_app_instance_created',
                'name_ar' => 'إنشاء كائن Flask للتطبيق',
                'name_en' => 'Create Flask application instance',
                'description_ar' => 'يشرح إنشاء التطبيق بواسطة Flask(__name__).',
                'claim_ar' => 'ينشئ الطالب تطبيق Flask باستخدام app = Flask(__name__) أو Flask(__name__).',
                'claim_en' => 'The student creates a Flask app instance using app = Flask(__name__) or Flask(__name__).',
            ],
            [
                'code' => 'flask_root_route_defined',
                'name_ar' => 'تعريف route للمسار الرئيسي',
                'name_en' => 'Define root route',
                'description_ar' => 'يذكر استخدام @app.route("/") لربط المسار الرئيسي بدالة.',
                'claim_ar' => 'يعرّف الطالب route للمسار / باستخدام @app.route("/") أو @app.route(\'/\').',
                'claim_en' => 'The student defines the root URL route using @app.route("/") or @app.route(\'/\').',
            ],
            [
                'code' => 'flask_hello_response_returned',
                'name_ar' => 'إرجاع Hello World من الدالة',
                'name_en' => 'Return Hello World response',
                'description_ar' => 'يذكر أن دالة route تعيد Hello World كاستجابة HTTP.',
                'claim_ar' => 'يذكر الطالب أن دالة الـ route تعيد النص "Hello World" باستخدام return، وليس print فقط.',
                'claim_en' => 'The student says the route function returns "Hello World" using return, rather than only printing it.',
            ],
            [
                'code' => 'flask_app_run_local',
                'name_ar' => 'تشغيل التطبيق محليًا',
                'name_en' => 'Run Flask app locally',
                'description_ar' => 'يذكر تشغيل التطبيق محليًا عبر app.run أو flask run.',
                'claim_ar' => 'يشغّل الطالب التطبيق محليًا باستخدام app.run() أو الأمر flask run.',
                'claim_en' => 'The student runs the app locally using app.run() or the flask run command.',
            ],
            [
                'code' => 'flask_console_print_is_web_response_claim',
                'name_ar' => 'ادعاء خاطئ بأن print يكفي كاستجابة ويب',
                'name_en' => 'Print alone is web response claim',
                'description_ar' => 'ادعاء خاطئ بأن طباعة النص في console تجعلها استجابة HTTP.',
                'claim_ar' => 'يدّعي الطالب أن print("Hello World") وحدها، دون route وreturn، تجعل صفحة Flask تعرض النص للمتصفح.',
                'claim_en' => 'The student claims that print("Hello World") alone, without a route and return, makes Flask display the text in a browser.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_HELLO_APP_INSTANCE',
                'name_ar' => 'إنشاء تطبيق Flask',
                'description_ar' => 'ينشئ كائن التطبيق بصورة صحيحة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_app_instance_created',
                ],
                'blocked_by' => [],
                'sample_good' => 'app = Flask(__name__)',
                'sample_bad' => 'app = Flask',
                'feedback_pass' => 'وضحت إنشاء كائن تطبيق Flask.',
                'feedback_fail' => 'أنشئ كائن التطبيق باستخدام Flask(__name__).',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_HELLO_ROOT_ROUTE',
                'name_ar' => 'تعريف المسار الرئيسي',
                'description_ar' => 'يربط المسار / بدالة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_root_route_defined',
                ],
                'blocked_by' => [
                    'flask_console_print_is_web_response_claim',
                ],
                'sample_good' => '@app.route("/")',
                'sample_bad' => 'route("/")',
                'feedback_pass' => 'وضحت ربط المسار الرئيسي بدالة.',
                'feedback_fail' => 'أضف @app.route("/") لتعريف مسار الصفحة الرئيسية.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_HELLO_RESPONSE',
                'name_ar' => 'إرجاع استجابة Hello World',
                'description_ar' => 'تعيد الدالة النص المطلوب كاستجابة.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_hello_response_returned',
                ],
                'blocked_by' => [
                    'flask_console_print_is_web_response_claim',
                ],
                'sample_good' => 'return "Hello World"',
                'sample_bad' => 'print("Hello World") فقط',
                'feedback_pass' => 'وضحت إرجاع Hello World كاستجابة.',
                'feedback_fail' => 'استخدم return لإرجاع Hello World من دالة الـroute.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_HELLO_RUN_LOCAL',
                'name_ar' => 'تشغيل التطبيق محليًا',
                'description_ar' => 'يذكر طريقة تشغيل التطبيق أثناء التطوير.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_app_run_local',
                ],
                'blocked_by' => [],
                'sample_good' => 'app.run(debug=True)',
                'sample_bad' => 'لا يذكر التشغيل',
                'feedback_pass' => 'وضحت تشغيل التطبيق محليًا.',
                'feedback_fail' => 'اذكر app.run() أو flask run لتشغيل التطبيق محليًا.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_HELLO_CONFLICT_PRINT_ONLY',
                'trigger_concept' => 'flask_console_print_is_web_response_claim',
                'feedback_ar' => 'print تطبع في الطرفية فقط؛ لكي تظهر الاستجابة في المتصفح يجب تعريف route وإرجاع النص من الدالة.',
                'blocked_rubrics' => [
                    'FLASK_HELLO_ROOT_ROUTE',
                    'FLASK_HELLO_RESPONSE',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'ما وظيفة @app.route في Flask؟',
        'topic' => 'flask_routing',
        'rule_set_code' => 'FLASK_ROUTE_DECORATOR_V1',
        'concepts' => [
            [
                'code' => 'flask_route_maps_url_to_function',
                'name_ar' => 'ربط URL بدالة',
                'name_en' => 'Map URL to function',
                'description_ar' => 'يشرح أن @app.route تربط مسار URL بدالة view.',
                'claim_ar' => 'يذكر الطالب أن @app.route تربط مسار URL أو endpoint بدالة في Flask.',
                'claim_en' => 'The student states that @app.route maps a URL path or endpoint to a Flask function.',
            ],
            [
                'code' => 'flask_route_defines_endpoint_path',
                'name_ar' => 'تعريف مسار endpoint',
                'name_en' => 'Define endpoint path',
                'description_ar' => 'يوضح أن decorator تحدد المسار مثل /users أو /.',
                'claim_ar' => 'يذكر الطالب أن @app.route تحدد المسار الذي يصل إليه العميل مثل / أو /users.',
                'claim_en' => 'The student says that @app.route defines the path a client visits, such as / or /users.',
            ],
            [
                'code' => 'flask_route_function_handles_request',
                'name_ar' => 'دالة route تعالج الطلب وتعيد الاستجابة',
                'name_en' => 'Route function handles request and returns response',
                'description_ar' => 'يوضح دور الدالة المرتبطة بالمسار.',
                'claim_ar' => 'يذكر الطالب أن الدالة المرتبطة بـ route تستقبل أو تعالج الطلب ثم تعيد response.',
                'claim_en' => 'The student says the function associated with a route handles the request and returns a response.',
            ],
            [
                'code' => 'flask_route_valid_example',
                'name_ar' => 'مثال صحيح على @app.route',
                'name_en' => 'Valid @app.route example',
                'description_ar' => 'يعرض مثال decorator مع دالة Flask صحيحة.',
                'claim_ar' => 'يقدم الطالب مثالًا صحيحًا يتضمن @app.route ومسارًا ودالة تعيد استجابة.',
                'claim_en' => 'The student provides a valid example containing @app.route, a path, and a function returning a response.',
            ],
            [
                'code' => 'flask_route_starts_server_claim',
                'name_ar' => 'ادعاء خاطئ بأن route تشغل الخادم',
                'name_en' => 'Route starts server claim',
                'description_ar' => 'ادعاء خاطئ بأن @app.route هي أمر تشغيل الخادم.',
                'claim_ar' => 'يدّعي الطالب أن @app.route تشغّل خادم Flask أو أنها بديل عن app.run أو flask run.',
                'claim_en' => 'The student claims that @app.route starts the Flask server or replaces app.run/flask run.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_ROUTE_MAPS_URL',
                'name_ar' => 'ربط المسار بدالة',
                'description_ar' => 'يوضح الربط الأساسي بين URL والدالة.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_route_maps_url_to_function',
                ],
                'blocked_by' => [
                    'flask_route_starts_server_claim',
                ],
                'sample_good' => '@app.route("/users") تربط /users بدالة.',
                'sample_bad' => '@app.route تشغّل السيرفر.',
                'feedback_pass' => 'وضحت أن route تربط URL بدالة.',
                'feedback_fail' => 'وضّح أن @app.route تربط مسار URL بدالة.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_ROUTE_ENDPOINT_PATH',
                'name_ar' => 'تحديد مسار endpoint',
                'description_ar' => 'يوضح أن decorator تحدد المسار الذي يزوره العميل.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_route_defines_endpoint_path',
                ],
                'blocked_by' => [],
                'sample_good' => '@app.route("/")',
                'sample_bad' => 'لا يذكر المسار.',
                'feedback_pass' => 'وضحت دور @app.route في تحديد المسار.',
                'feedback_fail' => 'اذكر أن @app.route تحدد مسار endpoint مثل / أو /users.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_ROUTE_RESPONSE_HANDLER',
                'name_ar' => 'معالجة الطلب وإرجاع الاستجابة',
                'description_ar' => 'يربط الدالة بمعالجة الطلب والاستجابة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_route_function_handles_request',
                ],
                'blocked_by' => [],
                'sample_good' => 'الدالة تعيد response للطلب.',
                'sample_bad' => 'الدالة لا تعيد شيئًا.',
                'feedback_pass' => 'وضحت دور الدالة المرتبطة بالـroute.',
                'feedback_fail' => 'اذكر أن دالة الـroute تعالج الطلب وتعيد استجابة.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_ROUTE_VALID_EXAMPLE',
                'name_ar' => 'مثال صحيح',
                'description_ar' => 'يقدم مثال decorator متكاملًا.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_route_valid_example',
                ],
                'blocked_by' => [],
                'sample_good' => '@app.route("/")
def home(): return "Hi"',
                'sample_bad' => 'app.route بدون @ أو دالة.',
                'feedback_pass' => 'قدمت مثالًا صحيحًا على @app.route.',
                'feedback_fail' => 'أضف مثالًا فيه @app.route ودالة تعيد response.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_ROUTE_CONFLICT_STARTS_SERVER',
                'trigger_concept' => 'flask_route_starts_server_claim',
                'feedback_ar' => '@app.route لا تشغّل الخادم؛ وظيفتها ربط مسار URL بدالة، بينما التشغيل يتم عبر app.run أو flask run.',
                'blocked_rubrics' => [
                    'FLASK_ROUTE_MAPS_URL',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'كيف تشغّل تطبيق Flask محليًا أثناء التطوير؟',
        'topic' => 'flask_local_development',
        'rule_set_code' => 'FLASK_LOCAL_RUN_V1',
        'concepts' => [
            [
                'code' => 'flask_local_app_entry_exists',
                'name_ar' => 'وجود كائن التطبيق',
                'name_en' => 'Flask app object exists',
                'description_ar' => 'يذكر وجود كائن app قبل تشغيل التطبيق.',
                'claim_ar' => 'يذكر الطالب وجود كائن Flask للتطبيق مثل app = Flask(__name__) قبل تشغيله.',
                'claim_en' => 'The student mentions a Flask app object such as app = Flask(__name__) before running it.',
            ],
            [
                'code' => 'flask_local_run_command_or_app_run',
                'name_ar' => 'تشغيل الخادم محليًا',
                'name_en' => 'Run local server',
                'description_ar' => 'يذكر app.run أو flask run لتشغيل خادم التطوير.',
                'claim_ar' => 'يذكر الطالب تشغيل التطبيق باستخدام app.run() أو flask run.',
                'claim_en' => 'The student runs the app using app.run() or flask run.',
            ],
            [
                'code' => 'flask_local_debug_development',
                'name_ar' => 'استخدام debug أثناء التطوير',
                'name_en' => 'Use debug in development',
                'description_ar' => 'يذكر debug=True أو FLASK_DEBUG للتطوير وليس للإنتاج.',
                'claim_ar' => 'يذكر الطالب تفعيل debug=True أو وضع debug أثناء التطوير لإعادة التحميل ورؤية الأخطاء.',
                'claim_en' => 'The student mentions enabling debug=True or debug mode during development for reload/error visibility.',
            ],
            [
                'code' => 'flask_local_open_browser_address',
                'name_ar' => 'فتح عنوان محلي في المتصفح',
                'name_en' => 'Open local address in browser',
                'description_ar' => 'يذكر فتح localhost أو 127.0.0.1 في المتصفح بعد التشغيل.',
                'claim_ar' => 'يذكر الطالب فتح العنوان المحلي المعروض مثل http://127.0.0.1:5000 في المتصفح.',
                'claim_en' => 'The student says to open the shown local address, such as http://127.0.0.1:5000, in a browser.',
            ],
            [
                'code' => 'flask_local_no_server_needed_claim',
                'name_ar' => 'ادعاء خاطئ بأن Flask تعمل دون تشغيل خادم',
                'name_en' => 'No server needed claim',
                'description_ar' => 'ادعاء خاطئ بأن فتح ملف Python أو كتابة route يكفي لإتاحة التطبيق للمتصفح دون تشغيل Flask.',
                'claim_ar' => 'يدّعي الطالب أن تطبيق Flask يظهر في المتصفح تلقائيًا دون app.run أو flask run أو أي خادم تشغيل.',
                'claim_en' => 'The student claims a Flask app appears in the browser automatically without app.run, flask run, or any server command.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_LOCAL_APP_ENTRY',
                'name_ar' => 'وجود التطبيق',
                'description_ar' => 'يذكر كائن Flask الذي سيجري تشغيله.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_local_app_entry_exists',
                ],
                'blocked_by' => [],
                'sample_good' => 'app = Flask(__name__)',
                'sample_bad' => 'لا يوجد app.',
                'feedback_pass' => 'وضحت وجود كائن Flask للتطبيق.',
                'feedback_fail' => 'اذكر إنشاء كائن Flask للتطبيق قبل التشغيل.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_LOCAL_RUN_COMMAND',
                'name_ar' => 'أمر التشغيل المحلي',
                'description_ar' => 'يذكر app.run أو flask run.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_local_run_command_or_app_run',
                ],
                'blocked_by' => [
                    'flask_local_no_server_needed_claim',
                ],
                'sample_good' => 'flask run أو app.run(debug=True)',
                'sample_bad' => 'مجرد فتح ملف Python.',
                'feedback_pass' => 'وضحت طريقة تشغيل خادم Flask محليًا.',
                'feedback_fail' => 'اذكر app.run() أو flask run لتشغيل التطبيق محليًا.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_LOCAL_DEBUG',
                'name_ar' => 'وضع التطوير',
                'description_ar' => 'يوضح استعمال debug أثناء التطوير.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_local_debug_development',
                ],
                'blocked_by' => [
                    'flask_local_no_server_needed_claim',
                ],
                'sample_good' => 'app.run(debug=True)',
                'sample_bad' => 'debug في الإنتاج دائمًا.',
                'feedback_pass' => 'وضحت استخدام debug أثناء التطوير.',
                'feedback_fail' => 'اذكر debug=True أو FLASK_DEBUG أثناء التطوير.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_LOCAL_BROWSER_ADDRESS',
                'name_ar' => 'فتح التطبيق في المتصفح',
                'description_ar' => 'يذكر الوصول إلى عنوان localhost.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_local_open_browser_address',
                ],
                'blocked_by' => [],
                'sample_good' => 'افتح http://127.0.0.1:5000',
                'sample_bad' => 'لا يذكر الوصول.',
                'feedback_pass' => 'وضحت فتح عنوان التطبيق المحلي في المتصفح.',
                'feedback_fail' => 'اذكر فتح عنوان localhost أو 127.0.0.1 المعروض في المتصفح.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_LOCAL_CONFLICT_NO_SERVER',
                'trigger_concept' => 'flask_local_no_server_needed_claim',
                'feedback_ar' => 'يجب تشغيل خادم Flask عبر app.run أو flask run؛ تعريف route أو فتح الملف وحده لا يوفّر صفحة للمتصفح.',
                'blocked_rubrics' => [
                    'FLASK_LOCAL_RUN_COMMAND',
                    'FLASK_LOCAL_DEBUG',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'كيف تتعامل مع طلب POST في Flask وتقرأ بيانات JSON من الطلب؟',
        'topic' => 'flask_post_json',
        'rule_set_code' => 'FLASK_POST_JSON_V1',
        'concepts' => [
            [
                'code' => 'flask_post_route_allows_post',
                'name_ar' => 'تعريف route تقبل POST',
                'name_en' => 'Route accepts POST',
                'description_ar' => 'يذكر methods=["POST"] أو methods=[\'POST\'].',
                'claim_ar' => 'يعرّف الطالب route تقبل POST باستخدام methods=["POST"] أو methods=[\'POST\'].',
                'claim_en' => 'The student defines a route accepting POST using methods=["POST"] or methods=[\'POST\'].',
            ],
            [
                'code' => 'flask_post_reads_request_json',
                'name_ar' => 'قراءة JSON من request',
                'name_en' => 'Read JSON from request',
                'description_ar' => 'يذكر request.get_json() أو request.json لقراءة جسم JSON.',
                'claim_ar' => 'يقرأ الطالب بيانات JSON باستخدام request.get_json() أو request.json.',
                'claim_en' => 'The student reads JSON using request.get_json() or request.json.',
            ],
            [
                'code' => 'flask_post_validates_input',
                'name_ar' => 'التحقق من بيانات الإدخال',
                'name_en' => 'Validate input',
                'description_ar' => 'يذكر التحقق من الحقول المطلوبة أو الأنواع قبل المعالجة.',
                'claim_ar' => 'يتحقق الطالب من الحقول المطلوبة أو صحة البيانات بعد قراءة JSON وقبل تنفيذ منطق العمل.',
                'claim_en' => 'The student validates required fields or data correctness after reading JSON and before business processing.',
            ],
            [
                'code' => 'flask_post_returns_json_response',
                'name_ar' => 'إرجاع استجابة JSON مناسبة',
                'name_en' => 'Return JSON response',
                'description_ar' => 'يذكر jsonify واستجابة نجاح أو خطأ مناسبة.',
                'claim_ar' => 'يعيد الطالب استجابة JSON باستخدام jsonify مع كود حالة مناسب عند الحاجة.',
                'claim_en' => 'The student returns a JSON response using jsonify, with an appropriate status code when needed.',
            ],
            [
                'code' => 'flask_post_reads_json_from_query_claim',
                'name_ar' => 'ادعاء خاطئ بأن JSON تُقرأ من query parameters',
                'name_en' => 'Read JSON from query params claim',
                'description_ar' => 'ادعاء خاطئ بأن request.args هي المكان الطبيعي لقراءة جسم JSON في POST.',
                'claim_ar' => 'يدّعي الطالب أن بيانات JSON المرسلة في جسم POST تُقرأ عادةً من request.args أو من query parameters بدل request.get_json().',
                'claim_en' => 'The student claims JSON sent in a POST body is normally read from request.args or query parameters instead of request.get_json().',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_POST_METHOD',
                'name_ar' => 'قبول POST',
                'description_ar' => 'يعرّف route تقبل POST.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_post_route_allows_post',
                ],
                'blocked_by' => [],
                'sample_good' => '@app.route("/users", methods=["POST"])',
                'sample_bad' => '@app.route("/users") فقط',
                'feedback_pass' => 'وضحت تعريف route تقبل POST.',
                'feedback_fail' => 'أضف methods=["POST"] عند تعريف الـroute.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_POST_READ_JSON',
                'name_ar' => 'قراءة JSON',
                'description_ar' => 'يقرأ جسم الطلب بصيغة JSON.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_post_reads_request_json',
                ],
                'blocked_by' => [
                    'flask_post_reads_json_from_query_claim',
                ],
                'sample_good' => 'data = request.get_json()',
                'sample_bad' => 'data = request.args',
                'feedback_pass' => 'وضحت قراءة JSON من جسم الطلب.',
                'feedback_fail' => 'استخدم request.get_json() أو request.json لقراءة JSON.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_POST_VALIDATE',
                'name_ar' => 'التحقق من البيانات',
                'description_ar' => 'يتحقق من الحقول أو الأنواع قبل المعالجة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_post_validates_input',
                ],
                'blocked_by' => [
                    'flask_post_reads_json_from_query_claim',
                ],
                'sample_good' => 'if not data.get("name"): ...',
                'sample_bad' => 'استخدم البيانات دون تحقق.',
                'feedback_pass' => 'وضحت التحقق من بيانات الإدخال.',
                'feedback_fail' => 'تحقق من الحقول المطلوبة وصحة البيانات قبل المعالجة.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_POST_JSON_RESPONSE',
                'name_ar' => 'إرجاع JSON',
                'description_ar' => 'يعيد استجابة JSON واضحة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_post_returns_json_response',
                ],
                'blocked_by' => [],
                'sample_good' => 'return jsonify({"ok": True}), 201',
                'sample_bad' => 'return dict فقط دون توضيح.',
                'feedback_pass' => 'وضحت إرجاع استجابة JSON مناسبة.',
                'feedback_fail' => 'استخدم jsonify لإرجاع response JSON واضح.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_POST_CONFLICT_QUERY_FOR_JSON',
                'trigger_concept' => 'flask_post_reads_json_from_query_claim',
                'feedback_ar' => 'بيانات JSON في جسم POST تُقرأ بـ request.get_json() أو request.json؛ أما request.args فهي لبارامترات query string.',
                'blocked_rubrics' => [
                    'FLASK_POST_READ_JSON',
                    'FLASK_POST_VALIDATE',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'ما الفرق بين jsonify و إرجاع string عادي من route في Flask؟',
        'topic' => 'flask_jsonify_vs_string',
        'rule_set_code' => 'FLASK_JSONIFY_VS_STRING_V1',
        'concepts' => [
            [
                'code' => 'flask_jsonify_serializes_json',
                'name_ar' => 'jsonify تنشئ استجابة JSON',
                'name_en' => 'jsonify creates JSON response',
                'description_ar' => 'يوضح أن jsonify تحوّل البيانات إلى JSON وتجهز response مناسبًا.',
                'claim_ar' => 'يذكر الطالب أن jsonify تحول dict أو list إلى JSON وتعيد استجابة JSON.',
                'claim_en' => 'The student says jsonify converts a dict or list to JSON and returns a JSON response.',
            ],
            [
                'code' => 'flask_jsonify_sets_json_content_type',
                'name_ar' => 'تعيين Content-Type للتطبيق JSON',
                'name_en' => 'Set JSON content type',
                'description_ar' => 'يوضح أن jsonify تضبط Content-Type إلى application/json.',
                'claim_ar' => 'يذكر الطالب أن jsonify تضبط ترويسة Content-Type كـ application/json أو توضح نوع الاستجابة JSON.',
                'claim_en' => 'The student says jsonify sets Content-Type to application/json or otherwise marks the response as JSON.',
            ],
            [
                'code' => 'flask_plain_string_is_text_response',
                'name_ar' => 'string عادي هو استجابة نصية',
                'name_en' => 'Plain string is text response',
                'description_ar' => 'يوضح أن return "..." يعيد نصًا عاديًا وليس JSON منظمًا.',
                'claim_ar' => 'يذكر الطالب أن إرجاع string عادي يعيد نصًا عاديًا كاستجابة وليس JSON منظمًا.',
                'claim_en' => 'The student says returning a plain string produces a text response rather than structured JSON.',
            ],
            [
                'code' => 'flask_jsonify_api_use_case',
                'name_ar' => 'استخدام jsonify في API',
                'name_en' => 'Use jsonify for API',
                'description_ar' => 'يربط jsonify بإرجاع بيانات API منظمة للعميل.',
                'claim_ar' => 'يذكر الطالب أن jsonify مناسبة لإرجاع بيانات API مثل object أو list بصيغة JSON.',
                'claim_en' => 'The student says jsonify is suitable for returning API data such as an object or list as JSON.',
            ],
            [
                'code' => 'flask_jsonify_same_as_string_claim',
                'name_ar' => 'ادعاء خاطئ بأن jsonify وstring متطابقتان',
                'name_en' => 'jsonify identical to string claim',
                'description_ar' => 'ادعاء خاطئ بأن لا فرق في serialization أو نوع المحتوى.',
                'claim_ar' => 'يدّعي الطالب أن jsonify وإرجاع string عادي متطابقان تمامًا ولا فرق في JSON أو Content-Type أو الاستخدام.',
                'claim_en' => 'The student claims jsonify and returning a plain string are exactly identical, with no difference in JSON, Content-Type, or use.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_JSONIFY_JSON_RESPONSE',
                'name_ar' => 'إنتاج JSON',
                'description_ar' => 'يوضح أن jsonify تنتج استجابة JSON من بيانات Python.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_jsonify_serializes_json',
                ],
                'blocked_by' => [
                    'flask_jsonify_same_as_string_claim',
                ],
                'sample_good' => 'return jsonify({"name": "Ali"})',
                'sample_bad' => 'return str({"name": "Ali"})',
                'feedback_pass' => 'وضحت أن jsonify تنتج JSON من البيانات.',
                'feedback_fail' => 'وضّح أن jsonify تحول البيانات إلى استجابة JSON.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_JSONIFY_CONTENT_TYPE',
                'name_ar' => 'نوع محتوى JSON',
                'description_ar' => 'يوضح Content-Type أو دلالة الاستجابة JSON.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_jsonify_sets_json_content_type',
                ],
                'blocked_by' => [
                    'flask_jsonify_same_as_string_claim',
                ],
                'sample_good' => 'Content-Type: application/json',
                'sample_bad' => 'text/plain فقط',
                'feedback_pass' => 'وضحت أن jsonify تضبط نوع الاستجابة كـJSON.',
                'feedback_fail' => 'اذكر أن jsonify تضبط Content-Type كـ application/json.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_JSONIFY_STRING_RESPONSE',
                'name_ar' => 'طبيعة string العادي',
                'description_ar' => 'يوضح أن string استجابة نصية عادية.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_plain_string_is_text_response',
                ],
                'blocked_by' => [
                    'flask_jsonify_same_as_string_claim',
                ],
                'sample_good' => 'return "Hello" يعيد نصًا.',
                'sample_bad' => 'string هو JSON دائمًا.',
                'feedback_pass' => 'وضحت طبيعة إرجاع string عادي.',
                'feedback_fail' => 'وضّح أن string العادي يعيد نصًا وليس JSON منظمًا.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_JSONIFY_API_USE',
                'name_ar' => 'حالة استخدام API',
                'description_ar' => 'يربط jsonify بإرجاع بيانات API.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_jsonify_api_use_case',
                ],
                'blocked_by' => [],
                'sample_good' => 'jsonify مناسب لبيانات API.',
                'sample_bad' => 'استخدم string لكل API.',
                'feedback_pass' => 'وضحت متى نستخدم jsonify في API.',
                'feedback_fail' => 'اذكر أن jsonify مناسبة لإرجاع object أو list في API.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_JSONIFY_CONFLICT_IDENTICAL',
                'trigger_concept' => 'flask_jsonify_same_as_string_claim',
                'feedback_ar' => 'jsonify تنشئ استجابة JSON وتضبط نوع المحتوى المناسب، بينما string عادي يعيد نصًا عاديًا؛ لذلك ليستا متطابقتين.',
                'blocked_rubrics' => [
                    'FLASK_JSONIFY_JSON_RESPONSE',
                    'FLASK_JSONIFY_CONTENT_TYPE',
                    'FLASK_JSONIFY_STRING_RESPONSE',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'ما الفرق بين render_template و redirect في Flask؟',
        'topic' => 'flask_render_template_redirect',
        'rule_set_code' => 'FLASK_RENDER_TEMPLATE_REDIRECT_V1',
        'concepts' => [
            [
                'code' => 'flask_render_template_renders_html',
                'name_ar' => 'render_template تعرض قالب HTML',
                'name_en' => 'render_template renders HTML template',
                'description_ar' => 'يوضح أنها تعالج قالب HTML وترسله كاستجابة.',
                'claim_ar' => 'يذكر الطالب أن render_template تعرض أو ترندر قالب HTML وترسله للمستخدم كاستجابة.',
                'claim_en' => 'The student says render_template renders an HTML template and sends it to the user as a response.',
            ],
            [
                'code' => 'flask_redirect_sends_new_url',
                'name_ar' => 'redirect تعيد توجيه العميل لمسار آخر',
                'name_en' => 'redirect sends client to a new URL',
                'description_ar' => 'يوضح أنها تطلب من المتصفح الانتقال إلى URL آخر.',
                'claim_ar' => 'يذكر الطالب أن redirect تعيد توجيه المتصفح أو العميل إلى URL أو route آخر.',
                'claim_en' => 'The student says redirect instructs the browser/client to go to another URL or route.',
            ],
            [
                'code' => 'flask_redirect_new_request',
                'name_ar' => 'redirect تسبب طلبًا جديدًا',
                'name_en' => 'redirect causes new request',
                'description_ar' => 'يوضح أن redirect لا تعرض القالب مباشرة بل ينتقل العميل ويجري طلبًا جديدًا.',
                'claim_ar' => 'يذكر الطالب أن redirect تؤدي إلى انتقال العميل ثم طلب جديد للمسار الهدف، وليست عرضًا مباشرًا لقالب.',
                'claim_en' => 'The student says redirect causes the client to navigate and make a new request to the target route, rather than directly rendering a template.',
            ],
            [
                'code' => 'flask_render_redirect_use_difference',
                'name_ar' => 'التمييز في الاستخدام',
                'name_en' => 'Different use cases',
                'description_ar' => 'يوضح متى تعرض صفحة ومتى تعيد التوجيه مثل بعد POST.',
                'claim_ar' => 'يميّز الطالب بين استخدام render_template لعرض صفحة واستخدام redirect للانتقال لمسار آخر مثلًا بعد معالجة POST.',
                'claim_en' => 'The student distinguishes using render_template to show a page from redirect to move to another route, for example after a POST.',
            ],
            [
                'code' => 'flask_render_redirect_identical_claim',
                'name_ar' => 'ادعاء خاطئ بأن render_template وredirect متطابقتان',
                'name_en' => 'render_template and redirect identical claim',
                'description_ar' => 'ادعاء خاطئ بأن العمليتين تؤديان نفس السلوك.',
                'claim_ar' => 'يدّعي الطالب أن render_template وredirect تقومان بالشيء نفسه تمامًا ولا يوجد فرق في الانتقال أو عرض القالب.',
                'claim_en' => 'The student claims render_template and redirect do exactly the same thing, with no difference between navigation and rendering.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_RENDER_TEMPLATE',
                'name_ar' => 'عرض قالب HTML',
                'description_ar' => 'يوضح وظيفة render_template.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_render_template_renders_html',
                ],
                'blocked_by' => [
                    'flask_render_redirect_identical_claim',
                ],
                'sample_good' => 'return render_template("home.html")',
                'sample_bad' => 'render_template ينقل لمسار آخر.',
                'feedback_pass' => 'وضحت أن render_template تعرض قالب HTML.',
                'feedback_fail' => 'وضّح أن render_template ترندر قالب HTML وتعيده كاستجابة.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_REDIRECT',
                'name_ar' => 'إعادة توجيه العميل',
                'description_ar' => 'يوضح وظيفة redirect.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_redirect_sends_new_url',
                    'flask_redirect_new_request',
                ],
                'blocked_by' => [
                    'flask_render_redirect_identical_claim',
                ],
                'sample_good' => 'return redirect(url_for("home"))',
                'sample_bad' => 'redirect تعرض HTML نفسه.',
                'feedback_pass' => 'وضحت أن redirect تنقل العميل لمسار آخر.',
                'feedback_fail' => 'وضّح أن redirect تعيد توجيه العميل إلى URL آخر وتؤدي لطلب جديد.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_RENDER_REDIRECT_USE',
                'name_ar' => 'التمييز بين الاستخدامين',
                'description_ar' => 'يذكر حالة استخدام عملية للفرق.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_render_redirect_use_difference',
                ],
                'blocked_by' => [
                    'flask_render_redirect_identical_claim',
                ],
                'sample_good' => 'اعرض form بـrender_template ثم redirect بعد POST.',
                'sample_bad' => 'هما لنفس الاستخدام.',
                'feedback_pass' => 'وضحت الفرق العملي بين الاستخدامين.',
                'feedback_fail' => 'اذكر متى نعرض قالبًا ومتى نعيد توجيه العميل.',
                'order' => 3,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_RENDER_REDIRECT_CONFLICT_IDENTICAL',
                'trigger_concept' => 'flask_render_redirect_identical_claim',
                'feedback_ar' => 'render_template تعرض قالب HTML كاستجابة، أما redirect فتطلب من العميل الانتقال إلى URL آخر؛ لذلك لهما سلوكان مختلفان.',
                'blocked_rubrics' => [
                    'FLASK_RENDER_TEMPLATE',
                    'FLASK_REDIRECT',
                    'FLASK_RENDER_REDIRECT_USE',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'ما هي Blueprints في Flask؟ ولماذا نستخدمها؟',
        'topic' => 'flask_blueprints',
        'rule_set_code' => 'FLASK_BLUEPRINTS_V1',
        'concepts' => [
            [
                'code' => 'flask_blueprint_groups_related_routes',
                'name_ar' => 'Blueprint تجمع routes مرتبطة',
                'name_en' => 'Blueprint groups related routes',
                'description_ar' => 'يشرح أنها وحدة تنظّم routes/features مترابطة.',
                'claim_ar' => 'يذكر الطالب أن Blueprint تجمع routes أو views أو features مرتبطة في وحدة واحدة.',
                'claim_en' => 'The student says a Blueprint groups related routes, views, or features into one module.',
            ],
            [
                'code' => 'flask_blueprint_registered_on_app',
                'name_ar' => 'تسجيل Blueprint في التطبيق',
                'name_en' => 'Register Blueprint on app',
                'description_ar' => 'يذكر app.register_blueprint().',
                'claim_ar' => 'يذكر الطالب تسجيل Blueprint في التطبيق الرئيسي باستخدام app.register_blueprint(...).',
                'claim_en' => 'The student mentions registering a Blueprint on the main app using app.register_blueprint(...).',
            ],
            [
                'code' => 'flask_blueprint_modular_large_app',
                'name_ar' => 'تنظيم تطبيق كبير بشكل وحدات',
                'name_en' => 'Modularize large app',
                'description_ar' => 'يوضح فائدتها للتنظيم والفصل وإعادة الاستخدام في التطبيقات الكبيرة.',
                'claim_ar' => 'يذكر الطالب أن Blueprints تساعد على تنظيم التطبيق الكبير وفصل الميزات أو إعادة استخدام الوحدات.',
                'claim_en' => 'The student says Blueprints help organize a large app, separate features, or reuse modules.',
            ],
            [
                'code' => 'flask_blueprint_valid_example',
                'name_ar' => 'مثال صحيح على Blueprint',
                'name_en' => 'Valid Blueprint example',
                'description_ar' => 'يذكر إنشاء Blueprint وربط route أو تسجيلها.',
                'claim_ar' => 'يقدم الطالب مثالًا صحيحًا مثل bp = Blueprint(...) مع route أو app.register_blueprint(bp).',
                'claim_en' => 'The student provides a valid example such as bp = Blueprint(...) with a route or app.register_blueprint(bp).',
            ],
            [
                'code' => 'flask_blueprint_is_separate_server_claim',
                'name_ar' => 'ادعاء خاطئ بأن Blueprint خادم مستقل',
                'name_en' => 'Blueprint is separate server claim',
                'description_ar' => 'ادعاء خاطئ بأن Blueprint تشغّل تطبيق Flask مستقلًا أو خادمًا منفصلًا.',
                'claim_ar' => 'يدّعي الطالب أن Blueprint هي خادم Flask مستقل أو تطبيق منفصل يعمل دون تسجيله في التطبيق الرئيسي.',
                'claim_en' => 'The student claims a Blueprint is a separate Flask server or an independent app that works without registration on the main app.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_BLUEPRINT_GROUPS',
                'name_ar' => 'تعريف Blueprint',
                'description_ar' => 'يوضح أنها تجمع routes أو features مرتبطة.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_blueprint_groups_related_routes',
                ],
                'blocked_by' => [
                    'flask_blueprint_is_separate_server_claim',
                ],
                'sample_good' => 'Blueprint تجمع routes الخاصة بالمستخدمين.',
                'sample_bad' => 'Blueprint هي server جديد.',
                'feedback_pass' => 'وضحت أن Blueprint وحدة تجمع أجزاء مرتبطة من التطبيق.',
                'feedback_fail' => 'وضّح أن Blueprint تجمع routes أو features مرتبطة.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_BLUEPRINT_REGISTER',
                'name_ar' => 'تسجيل Blueprint',
                'description_ar' => 'يذكر تسجيلها في التطبيق الرئيسي.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_blueprint_registered_on_app',
                ],
                'blocked_by' => [
                    'flask_blueprint_is_separate_server_claim',
                ],
                'sample_good' => 'app.register_blueprint(users_bp)',
                'sample_bad' => 'تشغل Blueprint وحدها.',
                'feedback_pass' => 'وضحت تسجيل Blueprint في التطبيق.',
                'feedback_fail' => 'اذكر app.register_blueprint(...) لربطها بالتطبيق الرئيسي.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_BLUEPRINT_MODULARITY',
                'name_ar' => 'فائدة التنظيم',
                'description_ar' => 'يوضح فائدتها في التنظيم والتوسع.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_blueprint_modular_large_app',
                ],
                'blocked_by' => [],
                'sample_good' => 'تفصل auth عن users في تطبيق كبير.',
                'sample_bad' => 'تجمع كل شيء في ملف واحد.',
                'feedback_pass' => 'وضحت فائدة Blueprints في تنظيم التطبيق.',
                'feedback_fail' => 'اذكر أنها تساعد على فصل الميزات وتنظيم التطبيقات الكبيرة.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_BLUEPRINT_EXAMPLE',
                'name_ar' => 'مثال صحيح',
                'description_ar' => 'يقدم مثالًا عمليًا على Blueprint.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_blueprint_valid_example',
                ],
                'blocked_by' => [],
                'sample_good' => 'bp = Blueprint("users", __name__)',
                'sample_bad' => 'Blueprint() بلا اسم أو تسجيل.',
                'feedback_pass' => 'قدمت مثالًا صحيحًا على Blueprint.',
                'feedback_fail' => 'أضف مثالًا لإنشاء Blueprint أو تسجيلها.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_BLUEPRINT_CONFLICT_SEPARATE_SERVER',
                'trigger_concept' => 'flask_blueprint_is_separate_server_claim',
                'feedback_ar' => 'Blueprint ليست خادمًا مستقلًا؛ هي وحدة تنظيم تُسجّل داخل تطبيق Flask الرئيسي.',
                'blocked_rubrics' => [
                    'FLASK_BLUEPRINT_GROUPS',
                    'FLASK_BLUEPRINT_REGISTER',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'كيف تضيف معالجة أخطاء بسيطة في Flask مثل 404 أو 500؟',
        'topic' => 'flask_error_handling',
        'rule_set_code' => 'FLASK_ERROR_HANDLING_V1',
        'concepts' => [
            [
                'code' => 'flask_errorhandler_decorator',
                'name_ar' => 'استخدام @app.errorhandler',
                'name_en' => 'Use @app.errorhandler',
                'description_ar' => 'يذكر decorator لمعالجة رقم خطأ مثل 404 أو 500.',
                'claim_ar' => 'يستخدم الطالب @app.errorhandler(404) أو @app.errorhandler(500) لتعريف معالج خطأ.',
                'claim_en' => 'The student uses @app.errorhandler(404) or @app.errorhandler(500) to define an error handler.',
            ],
            [
                'code' => 'flask_error_handler_function',
                'name_ar' => 'دالة لمعالجة الخطأ',
                'name_en' => 'Error handler function',
                'description_ar' => 'يذكر دالة تستقبل error أو exception مرتبطة بالمعالج.',
                'claim_ar' => 'يذكر الطالب دالة handler لمعالجة الخطأ، مثل def not_found(error): أو دالة مماثلة.',
                'claim_en' => 'The student mentions a handler function for the error, such as def not_found(error): or equivalent.',
            ],
            [
                'code' => 'flask_error_returns_status',
                'name_ar' => 'إرجاع استجابة مع status code',
                'name_en' => 'Return response with status code',
                'description_ar' => 'يوضح إرجاع رسالة أو JSON مع 404/500 الصحيح.',
                'claim_ar' => 'يعيد الطالب رسالة أو jsonify مع كود الحالة المناسب مثل 404 أو 500.',
                'claim_en' => 'The student returns a message or jsonify response with the appropriate status code such as 404 or 500.',
            ],
            [
                'code' => 'flask_error_controlled_response',
                'name_ar' => 'استجابة خطأ منظمة وآمنة',
                'name_en' => 'Controlled safe error response',
                'description_ar' => 'يوضح الهدف: استجابة منظمة بدلاً من crash أو كشف تفاصيل حساسة.',
                'claim_ar' => 'يذكر الطالب أن معالجة الخطأ تعطي استجابة منظمة للمستخدم وتمنع إظهار تفاصيل داخلية حساسة.',
                'claim_en' => 'The student says error handling provides a controlled response and avoids exposing sensitive internal details.',
            ],
            [
                'code' => 'flask_errors_need_no_handler_claim',
                'name_ar' => 'ادعاء خاطئ بأن الأخطاء لا تحتاج معالجة',
                'name_en' => 'No handler needed claim',
                'description_ar' => 'ادعاء خاطئ بأن Flask يجب أن تترك 404/500 دون معالج أو تعرض traceback للمستخدم.',
                'claim_ar' => 'يدّعي الطالب أن أخطاء 404 أو 500 لا تحتاج أي error handler أو أن عرض traceback الداخلي للمستخدم هو السلوك الصحيح.',
                'claim_en' => 'The student claims 404/500 errors need no handler or that exposing the internal traceback to users is correct.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_ERROR_DECORATOR',
                'name_ar' => 'تعريف معالج الخطأ',
                'description_ar' => 'يستخدم @app.errorhandler مع حالة خطأ.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_errorhandler_decorator',
                ],
                'blocked_by' => [
                    'flask_errors_need_no_handler_claim',
                ],
                'sample_good' => '@app.errorhandler(404)',
                'sample_bad' => 'app.errorhandler بلا @ أو بلا رقم.',
                'feedback_pass' => 'وضحت تعريف error handler في Flask.',
                'feedback_fail' => 'استخدم @app.errorhandler(404) أو @app.errorhandler(500).',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_ERROR_HANDLER_FUNCTION',
                'name_ar' => 'دالة المعالجة',
                'description_ar' => 'يحدد دالة تستقبل الخطأ وتعالج الحالة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_error_handler_function',
                ],
                'blocked_by' => [],
                'sample_good' => 'def not_found(error):',
                'sample_bad' => 'لا توجد دالة handler.',
                'feedback_pass' => 'وضحت وجود دالة لمعالجة الخطأ.',
                'feedback_fail' => 'أضف دالة handler مثل def not_found(error):',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_ERROR_STATUS_RESPONSE',
                'name_ar' => 'استجابة وكود الحالة',
                'description_ar' => 'يعيد رسالة أو JSON مع status صحيح.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_error_returns_status',
                ],
                'blocked_by' => [
                    'flask_errors_need_no_handler_claim',
                ],
                'sample_good' => 'return jsonify({"error": "Not found"}), 404',
                'sample_bad' => 'return "error" فقط بلا status.',
                'feedback_pass' => 'وضحت إرجاع استجابة مع كود حالة صحيح.',
                'feedback_fail' => 'أعد response مع كود الحالة المناسب مثل 404 أو 500.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_ERROR_SAFE_RESPONSE',
                'name_ar' => 'هدف المعالجة المنظمة',
                'description_ar' => 'يوضح فائدة عدم كشف التفاصيل الداخلية.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_error_controlled_response',
                ],
                'blocked_by' => [
                    'flask_errors_need_no_handler_claim',
                ],
                'sample_good' => 'رسالة آمنة دون traceback داخلي.',
                'sample_bad' => 'اعرض traceback للمستخدم.',
                'feedback_pass' => 'وضحت هدف الاستجابة المنظمة للخطأ.',
                'feedback_fail' => 'اذكر أن الهدف إعطاء رسالة منظمة دون كشف تفاصيل داخلية.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_ERROR_CONFLICT_NO_HANDLER',
                'trigger_concept' => 'flask_errors_need_no_handler_claim',
                'feedback_ar' => 'من الأفضل تعريف error handlers لإرجاع استجابة منظمة وآمنة بدل عرض traceback أو ترك أخطاء 404/500 دون معالجة.',
                'blocked_rubrics' => [
                    'FLASK_ERROR_DECORATOR',
                    'FLASK_ERROR_STATUS_RESPONSE',
                    'FLASK_ERROR_SAFE_RESPONSE',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'كيف تتحقق من صحة بيانات الإدخال في API مبنية بـ Flask؟',
        'topic' => 'flask_input_validation',
        'rule_set_code' => 'FLASK_INPUT_VALIDATION_V1',
        'concepts' => [
            [
                'code' => 'flask_validation_reads_input',
                'name_ar' => 'قراءة بيانات الإدخال',
                'name_en' => 'Read input data',
                'description_ar' => 'يذكر قراءة JSON أو form قبل التحقق.',
                'claim_ar' => 'يقرأ الطالب بيانات الإدخال من request.get_json() أو مصدر الطلب المناسب قبل التحقق.',
                'claim_en' => 'The student reads input data using request.get_json() or the appropriate request source before validation.',
            ],
            [
                'code' => 'flask_validation_required_fields_types',
                'name_ar' => 'التحقق من الحقول والأنواع',
                'name_en' => 'Validate required fields and types',
                'description_ar' => 'يوضح فحص الحقول المطلوبة والأنواع والقيم المقبولة.',
                'claim_ar' => 'يتحقق الطالب من وجود الحقول المطلوبة ومن أنواعها أو قيمها المقبولة.',
                'claim_en' => 'The student validates required fields and their types or accepted values.',
            ],
            [
                'code' => 'flask_validation_returns_400',
                'name_ar' => 'إرجاع خطأ تحقق 400',
                'name_en' => 'Return 400 validation error',
                'description_ar' => 'يذكر إرجاع JSON واضح مع 400 عند فشل التحقق.',
                'claim_ar' => 'يعيد الطالب خطأ validation واضحًا مع status 400 عندما تكون البيانات غير صالحة.',
                'claim_en' => 'The student returns a clear validation error with status 400 when input is invalid.',
            ],
            [
                'code' => 'flask_validation_before_business_logic',
                'name_ar' => 'التحقق قبل المعالجة',
                'name_en' => 'Validate before business logic',
                'description_ar' => 'يوضح عدم تنفيذ business logic أو الحفظ قبل نجاح التحقق.',
                'claim_ar' => 'يذكر الطالب إجراء validation قبل حفظ البيانات أو تنفيذ business logic.',
                'claim_en' => 'The student says validation occurs before saving data or executing business logic.',
            ],
            [
                'code' => 'flask_validation_never_needed_claim',
                'name_ar' => 'ادعاء خاطئ بأن كل الإدخال موثوق',
                'name_en' => 'All input is trusted claim',
                'description_ar' => 'ادعاء خاطئ بأن API يجب أن تثق بكل بيانات العميل دون تحقق.',
                'claim_ar' => 'يدّعي الطالب أن بيانات العميل موثوقة دائمًا ولا حاجة للتحقق من الحقول أو الأنواع قبل المعالجة.',
                'claim_en' => 'The student claims client input is always trustworthy and needs no validation of fields or types before processing.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_VALIDATION_READ_INPUT',
                'name_ar' => 'قراءة بيانات الطلب',
                'description_ar' => 'يقرأ الإدخال من مصدره الصحيح.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_validation_reads_input',
                ],
                'blocked_by' => [],
                'sample_good' => 'data = request.get_json()',
                'sample_bad' => 'لا يقرأ البيانات.',
                'feedback_pass' => 'وضحت قراءة بيانات الإدخال.',
                'feedback_fail' => 'اذكر قراءة JSON أو مصدر بيانات الطلب قبل التحقق.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_VALIDATION_FIELDS_TYPES',
                'name_ar' => 'فحص الحقول والأنواع',
                'description_ar' => 'يتحقق من الحقول المطلوبة وصحة قيمها.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_validation_required_fields_types',
                ],
                'blocked_by' => [
                    'flask_validation_never_needed_claim',
                ],
                'sample_good' => 'if not isinstance(data.get("age"), int): ...',
                'sample_bad' => 'استخدم data مباشرة.',
                'feedback_pass' => 'وضحت التحقق من الحقول والأنواع.',
                'feedback_fail' => 'تحقق من الحقول المطلوبة وأنواعها أو قيمها.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_VALIDATION_400',
                'name_ar' => 'إرجاع 400 عند الخطأ',
                'description_ar' => 'يرجع رسالة validation مناسبة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_validation_returns_400',
                ],
                'blocked_by' => [
                    'flask_validation_never_needed_claim',
                ],
                'sample_good' => 'return jsonify({"error": "invalid"}), 400',
                'sample_bad' => 'return "bad" دون status.',
                'feedback_pass' => 'وضحت إرجاع خطأ validation مناسب.',
                'feedback_fail' => 'أعد استجابة واضحة مع status 400 للبيانات غير الصالحة.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_VALIDATION_BEFORE_LOGIC',
                'name_ar' => 'التحقق قبل التنفيذ',
                'description_ar' => 'يوضح ترتيب التحقق قبل الحفظ أو business logic.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_validation_before_business_logic',
                ],
                'blocked_by' => [
                    'flask_validation_never_needed_claim',
                ],
                'sample_good' => 'تحقق قبل إنشاء المستخدم في DB.',
                'sample_bad' => 'احفظ ثم تحقق.',
                'feedback_pass' => 'وضحت تنفيذ التحقق قبل منطق العمل.',
                'feedback_fail' => 'اذكر أن validation يجب أن تسبق الحفظ أو business logic.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_VALIDATION_CONFLICT_TRUST_ALL',
                'trigger_concept' => 'flask_validation_never_needed_claim',
                'feedback_ar' => 'بيانات العميل غير موثوقة؛ يجب التحقق من الحقول والأنواع قبل تنفيذ منطق العمل أو الحفظ.',
                'blocked_rubrics' => [
                    'FLASK_VALIDATION_FIELDS_TYPES',
                    'FLASK_VALIDATION_400',
                    'FLASK_VALIDATION_BEFORE_LOGIC',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'كيف تنظّم إعدادات التطبيق في Flask بين بيئة التطوير والإنتاج؟',
        'topic' => 'flask_environment_configuration',
        'rule_set_code' => 'FLASK_ENV_CONFIG_V1',
        'concepts' => [
            [
                'code' => 'flask_config_separate_classes_or_files',
                'name_ar' => 'فصل إعدادات البيئات',
                'name_en' => 'Separate environment configs',
                'description_ar' => 'يذكر Config classes أو ملفات إعدادات منفصلة للتطوير والإنتاج.',
                'claim_ar' => 'يذكر الطالب استخدام classes أو ملفات Config منفصلة لبيئة التطوير والإنتاج.',
                'claim_en' => 'The student mentions using separate Config classes or files for development and production.',
            ],
            [
                'code' => 'flask_config_select_environment',
                'name_ar' => 'اختيار الإعدادات حسب البيئة',
                'name_en' => 'Select config per environment',
                'description_ar' => 'يذكر تحميل configuration مناسب عبر environment variable أو factory.',
                'claim_ar' => 'يذكر الطالب اختيار وتحميل إعدادات التطوير أو الإنتاج حسب environment variable أو app factory.',
                'claim_en' => 'The student says the development or production configuration is selected and loaded based on an environment variable or app factory.',
            ],
            [
                'code' => 'flask_config_env_for_secrets',
                'name_ar' => 'استخدام environment variables للأسرار',
                'name_en' => 'Use env vars for secrets',
                'description_ar' => 'يذكر تخزين secrets مثل SECRET_KEY وDB URL في environment variables لا في الكود.',
                'claim_ar' => 'يذكر الطالب وضع الأسرار مثل SECRET_KEY أو database URL في environment variables بدل hardcoding في الكود.',
                'claim_en' => 'The student says secrets such as SECRET_KEY or database URL belong in environment variables instead of hardcoded source.',
            ],
            [
                'code' => 'flask_config_production_safe_debug',
                'name_ar' => 'إعدادات إنتاج آمنة',
                'name_en' => 'Safe production configuration',
                'description_ar' => 'يذكر إيقاف debug في الإنتاج واستخدام إعدادات مناسبة.',
                'claim_ar' => 'يذكر الطالب إيقاف debug في الإنتاج أو عدم تشغيل إعدادات التطوير في production.',
                'claim_en' => 'The student says debug should be off in production or that development settings should not be used in production.',
            ],
            [
                'code' => 'flask_config_same_everywhere_claim',
                'name_ar' => 'ادعاء خاطئ باستخدام نفس الإعدادات وdebug دائمًا',
                'name_en' => 'Same config and debug always claim',
                'description_ar' => 'ادعاء خاطئ بأن نفس config وdebug=True تناسب كل البيئات ومنها الإنتاج.',
                'claim_ar' => 'يدّعي الطالب أن نفس الإعدادات وdebug=True يجب أن تستخدم دائمًا في التطوير والإنتاج ولا حاجة لفصل البيئات.',
                'claim_en' => 'The student claims the same configuration and debug=True should always be used in development and production, with no need to separate environments.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_CONFIG_SEPARATE',
                'name_ar' => 'فصل الإعدادات',
                'description_ar' => 'يذكر فصل Config للتطوير والإنتاج.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_config_separate_classes_or_files',
                ],
                'blocked_by' => [
                    'flask_config_same_everywhere_claim',
                ],
                'sample_good' => 'class DevelopmentConfig وProductionConfig',
                'sample_bad' => 'ملف واحد بنفس القيم دائمًا.',
                'feedback_pass' => 'وضحت فصل إعدادات البيئات.',
                'feedback_fail' => 'استخدم Config classes أو ملفات منفصلة للتطوير والإنتاج.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_CONFIG_SELECT',
                'name_ar' => 'تحميل إعدادات البيئة',
                'description_ar' => 'يوضح اختيار config حسب البيئة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_config_select_environment',
                ],
                'blocked_by' => [],
                'sample_good' => 'app.config.from_object(config_name)',
                'sample_bad' => 'لا يحدد بيئة.',
                'feedback_pass' => 'وضحت تحميل الإعدادات المناسبة للبيئة.',
                'feedback_fail' => 'اذكر اختيار config حسب environment variable أو app factory.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_CONFIG_SECRETS',
                'name_ar' => 'حماية الأسرار',
                'description_ar' => 'يضع الأسرار في environment variables.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_config_env_for_secrets',
                ],
                'blocked_by' => [],
                'sample_good' => 'SECRET_KEY من environment variable',
                'sample_bad' => 'SECRET_KEY داخل Git.',
                'feedback_pass' => 'وضحت عدم hardcode للأسرار.',
                'feedback_fail' => 'ضع الأسرار في environment variables وليس في الكود.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_CONFIG_PRODUCTION_DEBUG',
                'name_ar' => 'أمان production',
                'description_ar' => 'يوضح إيقاف debug في الإنتاج.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_config_production_safe_debug',
                ],
                'blocked_by' => [
                    'flask_config_same_everywhere_claim',
                ],
                'sample_good' => 'DEBUG = False في production',
                'sample_bad' => 'DEBUG = True في production.',
                'feedback_pass' => 'وضحت إعداد production الآمن.',
                'feedback_fail' => 'اذكر إيقاف debug في الإنتاج.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_CONFIG_CONFLICT_SAME_EVERYWHERE',
                'trigger_concept' => 'flask_config_same_everywhere_claim',
                'feedback_ar' => 'لا يجب استخدام debug=True ونفس الإعدادات في الإنتاج؛ افصل الإعدادات وخفف معلومات التطوير واحفظ الأسرار خارج الكود.',
                'blocked_rubrics' => [
                    'FLASK_CONFIG_SEPARATE',
                    'FLASK_CONFIG_PRODUCTION_DEBUG',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'اشرح بشكل مبسط كيف يمكن إضافة authentication إلى API في Flask.',
        'topic' => 'flask_api_authentication',
        'rule_set_code' => 'FLASK_API_AUTHENTICATION_V1',
        'concepts' => [
            [
                'code' => 'flask_auth_credentials_or_token',
                'name_ar' => 'اعتماد token أو credentials',
                'name_en' => 'Use token or credentials',
                'description_ar' => 'يذكر تسجيل الدخول أو token مثل JWT/API token للتحقق من الهوية.',
                'claim_ar' => 'يذكر الطالب استخدام credentials لتسجيل الدخول أو token مثل JWT/API token لتمثيل هوية المستخدم.',
                'claim_en' => 'The student mentions login credentials or a token such as JWT/API token to represent user identity.',
            ],
            [
                'code' => 'flask_auth_reads_authorization_header',
                'name_ar' => 'قراءة Authorization header',
                'name_en' => 'Read Authorization header',
                'description_ar' => 'يذكر إرسال token في Authorization header مثل Bearer.',
                'claim_ar' => 'يذكر الطالب إرسال أو قراءة token من Authorization header مثل Bearer token.',
                'claim_en' => 'The student says a token is sent or read from the Authorization header, such as a Bearer token.',
            ],
            [
                'code' => 'flask_auth_verify_before_access',
                'name_ar' => 'التحقق قبل السماح بالوصول',
                'name_en' => 'Verify before access',
                'description_ar' => 'يوضح التحقق من token أو الهوية قبل تنفيذ endpoint محمي.',
                'claim_ar' => 'يتحقق الطالب من token أو هوية المستخدم قبل تنفيذ منطق endpoint المحمي.',
                'claim_en' => 'The student verifies the token or user identity before executing a protected endpoint.',
            ],
            [
                'code' => 'flask_auth_reject_unauthorized',
                'name_ar' => 'رفض غير المصرح لهم',
                'name_en' => 'Reject unauthorized users',
                'description_ar' => 'يذكر 401 أو 403 عند غياب أو بطلان credentials/token.',
                'claim_ar' => 'يذكر الطالب إرجاع 401 أو 403 عند غياب token أو عدم صحته أو عدم وجود صلاحية.',
                'claim_en' => 'The student mentions returning 401 or 403 for missing/invalid token or insufficient permission.',
            ],
            [
                'code' => 'flask_auth_all_requests_allowed_claim',
                'name_ar' => 'ادعاء خاطئ بالسماح للجميع دون تحقق',
                'name_en' => 'Allow all without verification claim',
                'description_ar' => 'ادعاء خاطئ بأن endpoint المحمي يقبل أي طلب دون token أو فحص هوية.',
                'claim_ar' => 'يدّعي الطالب أن API المحمية يجب أن تسمح لكل الطلبات دون token أو دون التحقق من هوية المستخدم.',
                'claim_en' => 'The student claims a protected API should allow all requests without a token or identity verification.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_AUTH_CREDENTIAL_TOKEN',
                'name_ar' => 'اعتماد أو token',
                'description_ar' => 'يوضح وجود credential أو token للتحقق من الهوية.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_auth_credentials_or_token',
                ],
                'blocked_by' => [
                    'flask_auth_all_requests_allowed_claim',
                ],
                'sample_good' => 'استخدم JWT بعد login.',
                'sample_bad' => 'لا توجد هوية.',
                'feedback_pass' => 'وضحت استخدام credential أو token للمصادقة.',
                'feedback_fail' => 'اذكر credentials أو token مثل JWT/API token.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_AUTH_HEADER',
                'name_ar' => 'Authorization header',
                'description_ar' => 'يذكر إرسال أو قراءة token من header.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_auth_reads_authorization_header',
                ],
                'blocked_by' => [],
                'sample_good' => 'Authorization: Bearer <token>',
                'sample_bad' => 'token في URL فقط.',
                'feedback_pass' => 'وضحت استخدام Authorization header.',
                'feedback_fail' => 'اذكر Authorization: Bearer <token> أو قراءة header.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_AUTH_VERIFY',
                'name_ar' => 'التحقق قبل الوصول',
                'description_ar' => 'يتحقق من token قبل endpoint محمي.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_auth_verify_before_access',
                ],
                'blocked_by' => [
                    'flask_auth_all_requests_allowed_claim',
                ],
                'sample_good' => 'تحقق من JWT قبل تنفيذ route.',
                'sample_bad' => 'نفذ ثم تحقق.',
                'feedback_pass' => 'وضحت التحقق من الهوية قبل الوصول.',
                'feedback_fail' => 'اذكر التحقق من token قبل تنفيذ route المحمي.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_AUTH_REJECT',
                'name_ar' => 'رفض غير المصرح لهم',
                'description_ar' => 'يذكر 401 أو 403 للحالات غير المصرح بها.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_auth_reject_unauthorized',
                ],
                'blocked_by' => [
                    'flask_auth_all_requests_allowed_claim',
                ],
                'sample_good' => 'return jsonify(...), 401',
                'sample_bad' => 'اسمح للجميع.',
                'feedback_pass' => 'وضحت الاستجابة لطلبات غير المصرح لهم.',
                'feedback_fail' => 'اذكر 401 أو 403 عند غياب token أو عدم صلاحيته.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_AUTH_CONFLICT_ALLOW_ALL',
                'trigger_concept' => 'flask_auth_all_requests_allowed_claim',
                'feedback_ar' => 'الـAPI المحمية يجب أن تتحقق من هوية أو token قبل السماح بالوصول، وأن ترفض الطلب غير المصرح به بـ401 أو 403.',
                'blocked_rubrics' => [
                    'FLASK_AUTH_CREDENTIAL_TOKEN',
                    'FLASK_AUTH_VERIFY',
                    'FLASK_AUTH_REJECT',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'ما الأمور التي تجعل تطبيق Flask أقرب لأن يكون جاهزًا للإنتاج؟',
        'topic' => 'flask_production_readiness',
        'rule_set_code' => 'FLASK_PRODUCTION_READINESS_V1',
        'concepts' => [
            [
                'code' => 'flask_prod_wsgi_server',
                'name_ar' => 'خادم WSGI للإنتاج',
                'name_en' => 'Use production WSGI server',
                'description_ar' => 'يذكر gunicorn أو uWSGI أو خادم WSGI بدل dev server.',
                'claim_ar' => 'يذكر الطالب استخدام خادم WSGI مثل Gunicorn أو uWSGI بدل خادم Flask التطويري في الإنتاج.',
                'claim_en' => 'The student mentions using a WSGI server such as Gunicorn or uWSGI instead of Flask’s development server in production.',
            ],
            [
                'code' => 'flask_prod_debug_off_secrets_safe',
                'name_ar' => 'إيقاف debug وحماية الأسرار',
                'name_en' => 'Debug off and secrets safe',
                'description_ar' => 'يذكر DEBUG=False وحفظ الأسرار خارج الكود.',
                'claim_ar' => 'يذكر الطالب إيقاف debug في production وعدم hardcode للأسرار أو تحميلها من environment variables.',
                'claim_en' => 'The student says debug is disabled in production and secrets are not hardcoded but loaded from environment variables.',
            ],
            [
                'code' => 'flask_prod_logging_error_handling',
                'name_ar' => 'logging ومعالجة الأخطاء',
                'name_en' => 'Logging and error handling',
                'description_ar' => 'يذكر logging أو error handling لمتابعة الأعطال دون كشف تفاصيل.',
                'claim_ar' => 'يذكر الطالب إعداد logging ومعالجة أخطاء منظمة لمراقبة المشاكل وإرجاع رسائل آمنة.',
                'claim_en' => 'The student mentions logging and controlled error handling to monitor problems and return safe responses.',
            ],
            [
                'code' => 'flask_prod_tests_monitoring',
                'name_ar' => 'اختبارات ومراقبة',
                'name_en' => 'Tests and monitoring',
                'description_ar' => 'يذكر الاختبارات أو monitoring/health checks كجزء من الجاهزية.',
                'claim_ar' => 'يذكر الطالب الاختبارات الآلية أو monitoring أو health checks قبل أو أثناء التشغيل في الإنتاج.',
                'claim_en' => 'The student mentions automated tests, monitoring, or health checks as part of production readiness.',
            ],
            [
                'code' => 'flask_prod_dev_server_debug_claim',
                'name_ar' => 'ادعاء خاطئ باستخدام dev server وdebug في الإنتاج',
                'name_en' => 'Use dev server/debug in production claim',
                'description_ar' => 'ادعاء خاطئ بأن app.run(debug=True) هو إعداد الإنتاج الأفضل.',
                'claim_ar' => 'يدّعي الطالب أن خادم Flask التطويري مثل app.run(debug=True) مناسب أو الأفضل للاستخدام في production.',
                'claim_en' => 'The student claims Flask’s development server such as app.run(debug=True) is suitable or best for production.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_PROD_WSGI',
                'name_ar' => 'خادم إنتاج WSGI',
                'description_ar' => 'يذكر خادم WSGI مناسبًا للإنتاج.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_prod_wsgi_server',
                ],
                'blocked_by' => [
                    'flask_prod_dev_server_debug_claim',
                ],
                'sample_good' => 'Gunicorn يشغل التطبيق في production.',
                'sample_bad' => 'app.run(debug=True) في production.',
                'feedback_pass' => 'وضحت استخدام خادم WSGI للإنتاج.',
                'feedback_fail' => 'اذكر Gunicorn أو uWSGI بدل خادم التطوير.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_PROD_SAFE_CONFIG',
                'name_ar' => 'إعدادات آمنة',
                'description_ar' => 'يذكر إيقاف debug وحماية الأسرار.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_prod_debug_off_secrets_safe',
                ],
                'blocked_by' => [
                    'flask_prod_dev_server_debug_claim',
                ],
                'sample_good' => 'DEBUG=False والأسرار في env.',
                'sample_bad' => 'DEBUG=True وأسرار داخل الكود.',
                'feedback_pass' => 'وضحت إعدادات production الآمنة.',
                'feedback_fail' => 'اذكر DEBUG=False وعدم hardcode للأسرار.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_PROD_LOGGING',
                'name_ar' => 'التسجيل ومعالجة الأخطاء',
                'description_ar' => 'يذكر logging أو error handling منظمة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_prod_logging_error_handling',
                ],
                'blocked_by' => [],
                'sample_good' => 'logging للأخطاء ورسائل آمنة.',
                'sample_bad' => 'لا يوجد logging.',
                'feedback_pass' => 'وضحت أهمية logging ومعالجة الأخطاء.',
                'feedback_fail' => 'اذكر logging ومعالجة أخطاء مناسبة.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_PROD_TESTS_MONITORING',
                'name_ar' => 'اختبارات أو مراقبة',
                'description_ar' => 'يذكر الاختبار أو المراقبة.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_prod_tests_monitoring',
                ],
                'blocked_by' => [],
                'sample_good' => 'اختبارات وhealth checks.',
                'sample_bad' => 'لا يوجد اختبار أو مراقبة.',
                'feedback_pass' => 'وضحت دور الاختبارات أو المراقبة.',
                'feedback_fail' => 'اذكر اختبارات آلية أو monitoring/health checks.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_PROD_CONFLICT_DEV_SERVER',
                'trigger_concept' => 'flask_prod_dev_server_debug_claim',
                'feedback_ar' => 'خادم Flask التطويري وdebug=True ليسا مناسبين للإنتاج؛ استخدم WSGI server وأوقف debug واحمِ الأسرار.',
                'blocked_rubrics' => [
                    'FLASK_PROD_WSGI',
                    'FLASK_PROD_SAFE_CONFIG',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'كيف تصمم Flask service قابلة للتوسع إذا زاد عدد المستخدمين والطلبات؟',
        'topic' => 'flask_scalability',
        'rule_set_code' => 'FLASK_SCALABILITY_V1',
        'concepts' => [
            [
                'code' => 'flask_scale_multiple_instances_workers',
                'name_ar' => 'تشغيل عدة workers أو instances',
                'name_en' => 'Multiple workers or instances',
                'description_ar' => 'يذكر تعدد workers/instances لتوزيع الحمل.',
                'claim_ar' => 'يذكر الطالب تشغيل عدة workers أو instances من الخدمة بدل عملية واحدة فقط عند زيادة الحمل.',
                'claim_en' => 'The student says to run multiple workers or service instances instead of only one process as load grows.',
            ],
            [
                'code' => 'flask_scale_load_balancer',
                'name_ar' => 'موازن حمل',
                'name_en' => 'Use load balancer',
                'description_ar' => 'يذكر load balancer لتوزيع الطلبات بين instances.',
                'claim_ar' => 'يذكر الطالب استخدام load balancer لتوزيع الطلبات بين عدة instances أو workers.',
                'claim_en' => 'The student mentions using a load balancer to distribute requests among multiple instances or workers.',
            ],
            [
                'code' => 'flask_scale_stateless_external_state',
                'name_ar' => 'خدمة stateless وحالة خارجية',
                'name_en' => 'Stateless service and external state',
                'description_ar' => 'يوضح تجنب الحالة المحلية ووضع sessions/cache/DB في خدمات مشتركة.',
                'claim_ar' => 'يذكر الطالب جعل الخدمة stateless قدر الإمكان ووضع session أو cache أو state في مخزن خارجي مشترك مثل Redis أو قاعدة بيانات.',
                'claim_en' => 'The student says to keep the service stateless where possible and put session/cache/state in a shared external store such as Redis or a database.',
            ],
            [
                'code' => 'flask_scale_observability_and_autoscaling',
                'name_ar' => 'مراقبة وتوسعة حسب الحمل',
                'name_en' => 'Observability and autoscaling',
                'description_ar' => 'يذكر monitoring أو metrics أو autoscaling.',
                'claim_ar' => 'يذكر الطالب استخدام metrics/monitoring أو autoscaling لاتخاذ قرار التوسعة حسب الحمل.',
                'claim_en' => 'The student mentions metrics/monitoring or autoscaling to scale based on load.',
            ],
            [
                'code' => 'flask_scale_single_process_unlimited_claim',
                'name_ar' => 'ادعاء خاطئ بأن instance واحدة تكفي دائمًا',
                'name_en' => 'Single process unlimited claim',
                'description_ar' => 'ادعاء خاطئ بأن عملية Flask واحدة دون توزيع أو مراقبة تكفي لأي عدد من الطلبات.',
                'claim_ar' => 'يدّعي الطالب أن instance أو process Flask واحدة تكفي بلا حدود لأي عدد من المستخدمين والطلبات ولا حاجة لتوزيع الحمل أو التوسعة.',
                'claim_en' => 'The student claims one Flask instance or process is unlimited for any number of users/requests and needs no load distribution or scaling.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_SCALE_MULTI_INSTANCE',
                'name_ar' => 'عدة instances أو workers',
                'description_ar' => 'يوضح التوسع الأفقي أو تعدد workers.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_scale_multiple_instances_workers',
                ],
                'blocked_by' => [
                    'flask_scale_single_process_unlimited_claim',
                ],
                'sample_good' => 'شغّل عدة Gunicorn workers أو containers.',
                'sample_bad' => 'process واحد دائمًا.',
                'feedback_pass' => 'وضحت تشغيل عدة instances أو workers.',
                'feedback_fail' => 'اذكر تشغيل عدة workers أو instances عند زيادة الحمل.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_SCALE_LOAD_BALANCER',
                'name_ar' => 'توزيع الحمل',
                'description_ar' => 'يذكر موازن حمل بين instances.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_scale_load_balancer',
                ],
                'blocked_by' => [
                    'flask_scale_single_process_unlimited_claim',
                ],
                'sample_good' => 'Load balancer يوزع الطلبات.',
                'sample_bad' => 'كل الطلبات لعملية واحدة.',
                'feedback_pass' => 'وضحت استخدام load balancer.',
                'feedback_fail' => 'اذكر توزيع الطلبات عبر load balancer.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_SCALE_STATELESS',
                'name_ar' => 'فصل الحالة',
                'description_ar' => 'يوضح جعل الخدمة stateless ووضع الحالة خارجيًا.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_scale_stateless_external_state',
                ],
                'blocked_by' => [],
                'sample_good' => 'sessions في Redis بدل memory محلية.',
                'sample_bad' => 'state محلية في كل instance.',
                'feedback_pass' => 'وضحت إدارة الحالة عند التوسع.',
                'feedback_fail' => 'اذكر stateless أو Redis/DB مشترك للحالة.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_SCALE_OBSERVABILITY',
                'name_ar' => 'المراقبة والتوسعة',
                'description_ar' => 'يذكر monitoring أو autoscaling.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_scale_observability_and_autoscaling',
                ],
                'blocked_by' => [],
                'sample_good' => 'metrics ثم autoscaling.',
                'sample_bad' => 'لا تراقب الحمل.',
                'feedback_pass' => 'وضحت دور المراقبة أو autoscaling.',
                'feedback_fail' => 'اذكر monitoring أو metrics أو autoscaling.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_SCALE_CONFLICT_SINGLE_UNLIMITED',
                'trigger_concept' => 'flask_scale_single_process_unlimited_claim',
                'feedback_ar' => 'عملية واحدة ليست بلا حدود؛ عند زيادة الحمل نستخدم workers أو instances متعددة مع توزيع ومراقبة للحمل.',
                'blocked_rubrics' => [
                    'FLASK_SCALE_MULTI_INSTANCE',
                    'FLASK_SCALE_LOAD_BALANCER',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'ما أهمية الاختبارات في Flask project وكيف تنظمها؟',
        'topic' => 'flask_testing_organization',
        'rule_set_code' => 'FLASK_TESTING_V1',
        'concepts' => [
            [
                'code' => 'flask_test_verifies_routes_logic',
                'name_ar' => 'اختبار routes والمنطق',
                'name_en' => 'Test routes and logic',
                'description_ar' => 'يذكر اختبار السلوك المتوقع للroutes أو services.',
                'claim_ar' => 'يذكر الطالب أن الاختبارات تتحقق من سلوك routes أو business logic والنتائج المتوقعة.',
                'claim_en' => 'The student says tests verify expected behavior of routes or business logic.',
            ],
            [
                'code' => 'flask_test_uses_test_client_framework',
                'name_ar' => 'استخدام pytest/unittest وtest client',
                'name_en' => 'Use pytest/unittest and test client',
                'description_ar' => 'يذكر pytest أو unittest أو Flask test client.',
                'claim_ar' => 'يذكر الطالب استخدام pytest أو unittest و/أو Flask test client لإرسال طلبات اختبار للـAPI.',
                'claim_en' => 'The student mentions pytest or unittest and/or Flask’s test client for API test requests.',
            ],
            [
                'code' => 'flask_test_organized_tests_directory',
                'name_ar' => 'تنظيم ملفات الاختبار',
                'name_en' => 'Organize test files',
                'description_ar' => 'يذكر مجلد tests وتقسيم ملفات الاختبار حسب feature أو route.',
                'claim_ar' => 'يذكر الطالب وضع الاختبارات في مجلد tests وتنظيمها حسب feature أو route أو طبقة.',
                'claim_en' => 'The student says tests should be placed in a tests directory and organized by feature, route, or layer.',
            ],
            [
                'code' => 'flask_test_isolated_fixtures',
                'name_ar' => 'عزل الاختبارات وfixtures',
                'name_en' => 'Isolate tests with fixtures',
                'description_ar' => 'يذكر test config أو fixtures أو DB مؤقتة لعزل الاختبارات.',
                'claim_ar' => 'يذكر الطالب استخدام fixtures أو configuration اختبار أو قاعدة بيانات مؤقتة لعزل الاختبارات عن production.',
                'claim_en' => 'The student mentions fixtures, test configuration, or a temporary database to isolate tests from production.',
            ],
            [
                'code' => 'flask_test_manual_only_claim',
                'name_ar' => 'ادعاء خاطئ بأن الاختبار اليدوي فقط كافٍ',
                'name_en' => 'Manual-only testing claim',
                'description_ar' => 'ادعاء خاطئ بأن لا فائدة من الاختبارات الآلية أو أن النقر اليدوي وحده يكفي دائمًا.',
                'claim_ar' => 'يدّعي الطالب أن الاختبار اليدوي فقط يكفي دائمًا ولا حاجة لاختبارات آلية لroutes أو منطق Flask.',
                'claim_en' => 'The student claims manual testing alone is always enough and automated tests for Flask routes or logic are unnecessary.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_TEST_VALUE',
                'name_ar' => 'أهمية الاختبارات',
                'description_ar' => 'يوضح أنها تتحقق من السلوك المتوقع وتكشف regressions.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_test_verifies_routes_logic',
                ],
                'blocked_by' => [
                    'flask_test_manual_only_claim',
                ],
                'sample_good' => 'اختبر أن POST /users يعيد 201.',
                'sample_bad' => 'لا نحتاج نتائج متوقعة.',
                'feedback_pass' => 'وضحت أهمية اختبار السلوك المتوقع.',
                'feedback_fail' => 'اذكر أن الاختبارات تتحقق من routes والمنطق والنتائج المتوقعة.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_TEST_TOOLS',
                'name_ar' => 'أدوات الاختبار',
                'description_ar' => 'يذكر pytest/unittest أو test client.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_test_uses_test_client_framework',
                ],
                'blocked_by' => [],
                'sample_good' => 'pytest مع app.test_client().',
                'sample_bad' => 'اختبر من المتصفح فقط.',
                'feedback_pass' => 'وضحت استخدام أدوات اختبار مناسبة.',
                'feedback_fail' => 'اذكر pytest أو unittest أو Flask test client.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_TEST_ORGANIZATION',
                'name_ar' => 'تنظيم ملفات الاختبار',
                'description_ar' => 'يذكر مجلد tests وتقسيم واضح.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_test_organized_tests_directory',
                ],
                'blocked_by' => [],
                'sample_good' => 'tests/test_auth.py وtests/test_users.py',
                'sample_bad' => 'كل الاختبارات في ملف عشوائي.',
                'feedback_pass' => 'وضحت تنظيم ملفات الاختبار.',
                'feedback_fail' => 'اذكر وضع الاختبارات في مجلد tests وتقسيمها حسب الميزة أو route.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_TEST_ISOLATION',
                'name_ar' => 'عزل بيئة الاختبار',
                'description_ar' => 'يذكر fixtures أو DB/config اختبار.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_test_isolated_fixtures',
                ],
                'blocked_by' => [],
                'sample_good' => 'SQLite مؤقتة وfixtures.',
                'sample_bad' => 'استخدم production DB.',
                'feedback_pass' => 'وضحت عزل الاختبارات عن production.',
                'feedback_fail' => 'اذكر fixtures أو test config أو قاعدة بيانات مؤقتة.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_TEST_CONFLICT_MANUAL_ONLY',
                'trigger_concept' => 'flask_test_manual_only_claim',
                'feedback_ar' => 'الاختبار اليدوي مفيد لكنه لا يغني عن الاختبارات الآلية القابلة للتكرار التي تتحقق من routes والمنطق وتكشف regressions.',
                'blocked_rubrics' => [
                    'FLASK_TEST_VALUE',
                ],
            ],
        ],
    ],
    [
        'question_text' => 'إذا كنت تبني API كبيرة بـ Flask، كيف تفصل بين طبقة routes و business logic و data access؟',
        'topic' => 'flask_layered_architecture',
        'rule_set_code' => 'FLASK_LAYERED_ARCHITECTURE_V1',
        'concepts' => [
            [
                'code' => 'flask_layers_routes_http',
                'name_ar' => 'طبقة routes لطلبات HTTP',
                'name_en' => 'Routes layer handles HTTP',
                'description_ar' => 'يوضح أن routes تستقبل request، تستدعي service، وتعيد response دون منطق ثقيل.',
                'claim_ar' => 'يذكر الطالب أن طبقة routes تتعامل مع HTTP request/response وتستدعي service بدل وضع منطق العمل أو SQL الكامل داخلها.',
                'claim_en' => 'The student says routes handle HTTP requests/responses and call a service instead of containing all business logic or SQL.',
            ],
            [
                'code' => 'flask_layers_business_services',
                'name_ar' => 'طبقة services لمنطق العمل',
                'name_en' => 'Services layer for business logic',
                'description_ar' => 'يوضح وضع قواعد العمل في service/use-case functions.',
                'claim_ar' => 'يذكر الطالب وضع business logic وقواعد العمل في طبقة services أو use cases منفصلة.',
                'claim_en' => 'The student says business logic and rules belong in separate services or use-case functions.',
            ],
            [
                'code' => 'flask_layers_data_repositories',
                'name_ar' => 'طبقة data access',
                'name_en' => 'Data access/repositories layer',
                'description_ar' => 'يوضح وضع database queries/ORM في repository أو data access layer.',
                'claim_ar' => 'يذكر الطالب وضع استعلامات قاعدة البيانات أو ORM في repository أو data access layer منفصلة.',
                'claim_en' => 'The student says database queries or ORM code belong in a separate repository or data access layer.',
            ],
            [
                'code' => 'flask_layers_testability_boundaries',
                'name_ar' => 'فصل يسهل الاختبار والصيانة',
                'name_en' => 'Separation improves testability and maintenance',
                'description_ar' => 'يربط الفصل بقابلية الاختبار والصيانة وإعادة الاستخدام.',
                'claim_ar' => 'يذكر الطالب أن الفصل بين الطبقات يحسن الاختبار والصيانة وإعادة الاستخدام أو يقلل الترابط.',
                'claim_en' => 'The student says separating layers improves testability, maintenance, reuse, or reduces coupling.',
            ],
            [
                'code' => 'flask_layers_everything_in_route_claim',
                'name_ar' => 'ادعاء خاطئ بوضع كل شيء داخل route',
                'name_en' => 'Everything in route claim',
                'description_ar' => 'ادعاء خاطئ بأن route واحدة يجب أن تحتوي parsing وbusiness logic وSQL دون فصل.',
                'claim_ar' => 'يدّعي الطالب أنه من الأفضل وضع request handling وbusiness logic وكل database queries داخل route واحدة دون طبقات منفصلة.',
                'claim_en' => 'The student claims it is best to put request handling, business logic, and all database queries in a single route with no separate layers.',
            ],
        ],
        'rubrics' => [
            [
                'code' => 'FLASK_LAYER_ROUTES',
                'name_ar' => 'مسؤولية routes',
                'description_ar' => 'يوضح أن routes تتعامل مع HTTP وتنسق النداء.',
                'max_score' => 2.00,
                'requires' => [
                    'flask_layers_routes_http',
                ],
                'blocked_by' => [
                    'flask_layers_everything_in_route_claim',
                ],
                'sample_good' => 'route تقرأ request ثم تستدعي user_service.create().',
                'sample_bad' => 'route فيها SQL ومنطق كامل.',
                'feedback_pass' => 'وضحت مسؤولية طبقة routes.',
                'feedback_fail' => 'وضّح أن routes تتعامل مع HTTP وتستدعي طبقة service.',
                'order' => 1,
            ],
            [
                'code' => 'FLASK_LAYER_SERVICES',
                'name_ar' => 'مسؤولية business logic',
                'description_ar' => 'يضع قواعد العمل في services/use cases.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_layers_business_services',
                ],
                'blocked_by' => [
                    'flask_layers_everything_in_route_claim',
                ],
                'sample_good' => 'service تتحقق من قواعد إنشاء الطلب.',
                'sample_bad' => 'ضع قواعد العمل داخل route فقط.',
                'feedback_pass' => 'وضحت فصل business logic في services.',
                'feedback_fail' => 'اذكر وضع business logic في service أو use-case منفصل.',
                'order' => 2,
            ],
            [
                'code' => 'FLASK_LAYER_DATA_ACCESS',
                'name_ar' => 'مسؤولية data access',
                'description_ar' => 'يضع ORM أو queries في repository/data layer.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_layers_data_repositories',
                ],
                'blocked_by' => [
                    'flask_layers_everything_in_route_claim',
                ],
                'sample_good' => 'repository.get_by_id() ينفذ query.',
                'sample_bad' => 'SQL داخل route.',
                'feedback_pass' => 'وضحت فصل data access.',
                'feedback_fail' => 'اذكر repository أو data access layer للـORM والاستعلامات.',
                'order' => 3,
            ],
            [
                'code' => 'FLASK_LAYER_BENEFIT',
                'name_ar' => 'فائدة الفصل',
                'description_ar' => 'يذكر الاختبار والصيانة أو تقليل الترابط.',
                'max_score' => 1.00,
                'requires' => [
                    'flask_layers_testability_boundaries',
                ],
                'blocked_by' => [],
                'sample_good' => 'الفصل يسهل unit testing والصيانة.',
                'sample_bad' => 'لا فرق في الاختبار.',
                'feedback_pass' => 'وضحت فائدة الفصل بين الطبقات.',
                'feedback_fail' => 'اذكر أن الفصل يحسن الاختبار أو الصيانة أو يقلل الترابط.',
                'order' => 4,
            ],
        ],
        'contradictions' => [
            [
                'code' => 'FLASK_LAYER_CONFLICT_EVERYTHING_ROUTE',
                'trigger_concept' => 'flask_layers_everything_in_route_claim',
                'feedback_ar' => 'في API كبيرة لا تضع HTTP وbusiness logic وdata access كلها في route واحدة؛ افصلها إلى routes وservices وdata access لتحسين الصيانة والاختبار.',
                'blocked_rubrics' => [
                    'FLASK_LAYER_ROUTES',
                    'FLASK_LAYER_SERVICES',
                    'FLASK_LAYER_DATA_ACCESS',
                ],
            ],
        ],
    ],
];
    }
}

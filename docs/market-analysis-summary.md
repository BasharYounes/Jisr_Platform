\# Labor Market Analysis - Final Summary



\## 1. Feature Overview



ميزة \*\*Labor Market Analysis\*\* تهدف إلى تحليل بيانات سوق العمل وربطها بتقييم الطالب وخطة التعلم.



بدلاً من أن يحصل الطالب فقط على نتيجة تقييمه، أصبح النظام قادراً على توضيح ما إذا كانت المهارات التي يحتاج إلى تحسينها مطلوبة فعلاً في سوق العمل.



مثال:



إذا كان الطالب يحتاج إلى تحسين مهارة Flask، والنظام وجد أن Flask ظهرت في 80% من إعلانات Backend Developer، فسيتم عرض رسالة واضحة للطالب تشرح أن هذه المهارة مهمة في السوق ويُنصح بإعطائها أولوية في التعلم.



\---



\## 2. Problem Solved



قبل هذه الميزة، كانت توصيات الطالب تعتمد فقط على نتيجة التقييم الداخلي:



\- المستوى الحالي للطالب

\- المستوى المطلوب في المسار

\- الفجوة بينهما



بعد إضافة تحليل سوق العمل، أصبحت التوصيات أكثر واقعية لأنها تراعي أيضاً:



\- هل المهارة مطلوبة في الوظائف؟

\- كم مرة ظهرت المهارة في إعلانات العمل؟

\- هل المهارة أساسية أم مهمة أم داعمة؟

\- كيف يمكن شرح أهمية المهارة للطالب بلغة مفهومة؟



\---



\## 3. MVP Scope



تم تنفيذ النسخة الأولى من الميزة كـ MVP.



هذه النسخة تعتمد على Demo Dataset منظم بدلاً من scraping أو API خارجي.



الهدف من ذلك هو إثبات الفكرة بشكل آمن وقابل للاختبار، مع إبقاء التصميم قابلاً للتوسعة لاحقاً عند ربط API حقيقي لمصادر الوظائف.



\---



\## 4. Supported Career Paths



النسخة الحالية تدعم ثلاثة مسارات:



```text

Backend Developer

Frontend Developer

Mobile Developer

```



لكل مسار يوجد Dataset خاص به، ويتم تحليل المهارات المطلوبة داخله بشكل مستقل.



\---



\## 5. Main Capabilities



تم تنفيذ الإمكانيات التالية:



\- تخزين إعلانات الوظائف.

\- استخراج المهارات من عنوان ووصف الإعلان.

\- دعم استخراج المهارات باستخدام aliases.

\- دعم Arabic وEnglish aliases.

\- حفظ الأدلة التي تشرح لماذا تم اكتشاف المهارة.

\- حساب نسبة الطلب على كل مهارة.

\- إنشاء trend snapshots.

\- بناء skill map لكل career path.

\- عرض أكثر المهارات طلباً في كل مسار.

\- ربط بيانات السوق مع skill gaps.

\- ربط بيانات السوق مع learning path.

\- عرض رسائل مفهومة للطالب.

\- دعم fallback حسب normalized\_name عند اختلاف skill\_id.

\- تجهيز demo command يعيد بناء بيانات السوق بشكل repeatable.

\- إضافة tests تغطي السيناريوهات الأساسية.



\---



\## 6. Database Tables



\### 6.1 market\_job\_postings



يخزن إعلانات الوظائف التي تم استيرادها من Dataset أو من أي مصدر مستقبلي.



أهم الحقول:



```text

id

source\_type

source\_name

external\_id

title

description

company\_name

location

language

career\_path\_id

published\_at

status

content\_hash

```



\---



\### 6.2 market\_job\_posting\_skill\_occurrences



يخزن كل مهارة تم اكتشافها داخل إعلان وظيفة.



أهم الحقول:



```text

id

market\_job\_posting\_id

skill\_id

skill\_alias\_id

matched\_text

language

confidence

extraction\_method

context

```



هذا الجدول مهم لأنه لا يحفظ النتيجة فقط، بل يحفظ الدليل أيضاً.



مثلاً:



```text

Skill: Python

Matched Text: Python

Context: We are looking for a backend developer with Python, Flask, SQL...

```



\---



\### 6.3 market\_trends



يخزن snapshot للطلب على المهارات في تاريخ معين.



أهم الحقول:



```text

career\_path\_id

skill\_id

demand\_score

trend\_direction

source\_job\_count

analyzed\_date

```



\---



\## 7. Core Services



\### 7.1 MarketJobPostingImportService



مسؤول عن استيراد إعلانات الوظائف من ملف Dataset.



يقوم بـ:



\- قراءة بيانات الإعلان.

\- تحديد اللغة.

\- إنشاء content\_hash.

\- حفظ الإعلان أو تحديثه.

\- تشغيل استخراج المهارات بعد الاستيراد.



\---



\### 7.2 MarketSkillExtractionService



مسؤول عن استخراج المهارات من الإعلان.



يعتمد حالياً على alias matching.



يقوم بـ:



\- قراءة aliases من جدول skill\_aliases.

\- البحث عنها داخل عنوان ووصف الإعلان.

\- دعم العربية والإنجليزية.

\- دعم بعض حالات العربية مثل الأحرف المتصلة قبل الكلمة.

\- منع تكرار نفس المهارة أكثر من مرة داخل نفس الإعلان.

\- حفظ context يوضح أين ظهرت المهارة.



\---



\### 7.3 MarketInsightsService



مسؤول عن حساب نتائج تحليل السوق.



يقوم بـ:



\- حساب عدد الإعلانات لكل مسار.

\- حساب عدد مرات ظهور كل مهارة.

\- حساب demand\_percentage.

\- تصنيف المهارة إلى:

&#x20; - core

&#x20; - important

&#x20; - supporting

\- بناء skill\_map حسب category.



\---



\### 7.4 MarketTrendSnapshotService



مسؤول عن إنشاء snapshot يومية أو يدوية لنتائج السوق.



يقوم بـ:



\- قراءة نتائج demand الحالية.

\- حفظها في market\_trends.

\- تحديد trend\_direction:

&#x20; - new

&#x20; - rising

&#x20; - stable

&#x20; - falling



في نسخة MVP الحالية، أغلب النتائج تكون `new` لأننا نملك snapshot أولى فقط.



\---



\### 7.5 MarketSkillDemandContextService



مسؤول عن ربط بيانات السوق مع توصيات الطالب.



يستخدم داخل:



```text

SkillGapService

LearningPathService

```



يقوم بـ:



\- جلب آخر snapshot للمسار.

\- مطابقة مهارة الطالب مع مهارة السوق.

\- إذا لم يجد نفس skill\_id، يبحث حسب normalized\_name.

\- بناء market context مفهوم للطالب.

\- إرجاع market.available = false عند عدم توفر بيانات سوق للمهارة.



\---



\## 8. API Endpoints



\### 8.1 List Career Paths



```http

GET /api/market-analysis/career-paths?only\_with\_market\_data=1

```



يرجع المسارات التي لديها بيانات سوق عمل.



الفرونت يستخدم هذا endpoint للحصول على careerPathId.



\---



\### 8.2 Skill Demand



```http

GET /api/market-analysis/career-paths/{careerPathId}/skill-demand

```



يعرض المهارات المطلوبة في السوق لمسار معين.



يرجع:



\- skills

\- demand\_percentage

\- demand\_level

\- skill\_map



\---



\### 8.3 Trends



```http

GET /api/market-analysis/career-paths/{careerPathId}/trends

```



يعرض snapshot الطلب على المهارات.



\---



\### 8.4 Evidence



```http

GET /api/market-analysis/career-paths/{careerPathId}/skills/{skillId}/evidence

```



يعرض الأدلة التي تشرح سبب احتساب المهارة.



\---



\### 8.5 Skill Gaps With Market Context



```http

GET /api/assessments/{session}/skill-gaps

```



يرجع فجوات الطالب مع معلومات سوق العمل لكل مهارة.



\---



\### 8.6 Learning Path With Market Context



```http

GET /api/assessments/{session}/learning-path

```



يرجع خطة التعلم مع معلومات سوق العمل والموارد التعليمية.



\---



\## 9. Student-Facing Market Context



عندما تتوفر بيانات السوق للمهارة، يرجع النظام:



```json

{

&#x20; "available": true,

&#x20; "demand\_score": 100,

&#x20; "demand\_level": "core",

&#x20; "trend\_direction": "new",

&#x20; "source\_job\_count": 5,

&#x20; "sample\_size": 5,

&#x20; "student\_message": "مهارة Python مطلوبة في سوق العمل لمسار Backend Developer، حيث ظهرت في 5 من أصل 5 إعلانات وظائف تم تحليلها بنسبة طلب 100%. لذلك تعتبر من المهارات الأساسية التي يُنصح بإعطائها أولوية عالية في خطة التعلم."

}

```



هذا يجعل الـ response مناسباً للعرض المباشر داخل واجهة الطالب.



\---



\## 10. Missing Market Data Behavior



إذا كانت المهارة موجودة في تقييم الطالب لكنها غير موجودة في تحليل سوق العمل، لا يفشل النظام.



بدلاً من ذلك يرجع:



```json

{

&#x20; "available": false,

&#x20; "student\_message": "لا توجد حالياً بيانات سوق عمل كافية لهذه المهارة ضمن مسار Backend Developer. لذلك سيتم تحديد أولوية التعلم بناءً على نتيجة تقييمك فقط."

}

```



هذا مهم لأن skill gaps يجب أن تبقى مبنية على تقييم الطالب، حتى لو لم تكن بيانات السوق متوفرة لكل مهارة.



\---



\## 11. Demo Command



تم إنشاء command لتجهيز بيانات Demo بشكل كامل:



```bash

php artisan market:seed-demo-data --fresh

```



هذا الأمر يقوم بـ:



\- Seed dictionaries.

\- Import backend/frontend/mobile demo jobs.

\- Reanalyze job postings.

\- Create trend snapshots.

\- Clear old demo market data عند استخدام `--fresh`.



\---



\## 12. Final Validation



تم تشغيل الاختبارات النهائية على main بعد الدمج.



\### MarketAnalysis Tests



```text

Tests: 12 passed

Assertions: 290

```



\### LearningPathApiTest



```text

Tests: 2 passed

Assertions: 29

```



هذا يثبت أن:



\- Market APIs تعمل.

\- Demo command يعمل.

\- Frontend وMobile وBackend datasets صحيحة.

\- Extraction logic يعمل.

\- normalized\_name fallback يعمل.

\- Learning path يرجع market context.

\- صلاحيات الوصول للـ learning path محمية.



\---



\## 13. Current MVP Status



حالة الميزة:



```text

Completed as Backend MVP

```



المنجز:



\- Job posting storage

\- Skill extraction

\- Skill evidence

\- Demand calculation

\- Trend snapshots

\- Market APIs

\- Recommendation integration

\- Learning path integration

\- Student-friendly messages

\- Demo data

\- Regression tests

\- API documentation



\---



\## 14. Future Enhancements



الأشياء التالية ليست ضمن MVP الحالي، لكنها تحسينات مستقبلية:



\- ربط API حقيقي من منصة وظائف.

\- تشغيل scheduled weekly analysis.

\- إضافة admin dashboard لمراقبة جودة البيانات.

\- تحليل الرواتب والمناطق الجغرافية.

\- استخدام NLP أعمق من alias matching.

\- دعم datasets أكبر.

\- مقارنة تغير الطلب على المهارات عبر فترات زمنية متعددة.



\---



\## 15. Final Conclusion



تم تنفيذ ميزة Labor Market Analysis بنجاح كنسخة أولى.



الميزة أصبحت قادرة على تحليل بيانات سوق العمل، استخراج المهارات المطلوبة، حساب الطلب على كل مهارة، وبناء سياق واضح يساعد الطالب على فهم أهمية المهارات ضمن خطة التعلم.



التصميم الحالي قابل للتوسعة لاحقاً دون تغيير جذري، ويمكن استبدال Demo Dataset بمصدر API حقيقي عند توفره.


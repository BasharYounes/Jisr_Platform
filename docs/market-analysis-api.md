# Labor Market Analysis API Documentation

## 1. Overview

ميزة تحليل سوق العمل تقوم بتحليل إعلانات الوظائف، استخراج المهارات المطلوبة، حساب نسبة الطلب على كل مهارة، ثم ربط هذه النتائج مع توصيات الطالب.

الهدف من الميزة هو أن يعرف الطالب ليس فقط ما هي المهارات التي يحتاج إلى تحسينها، بل أيضاً هل هذه المهارات مطلوبة فعلاً في سوق العمل أم لا.

---

## 2. Authentication

كل endpoints التالية تحتاج Bearer Token:

```http
Authorization: Bearer {token}
Accept: application/json
```

---

# 3. Market Analysis Endpoints

---

## 3.1 List Career Paths With Market Data

### Endpoint

```http
GET /api/market-analysis/career-paths?only_with_market_data=1
```

### Purpose

يعرض المسارات المهنية التي لديها بيانات سوق عمل، مثل:

- Backend Developer
- Frontend Developer
- Mobile Developer

### Query Parameters

| Parameter | Type | Required | Description |
|---|---|---|---|
| only_with_market_data | boolean | No | عند إرسال 1 يرجع فقط المسارات التي لديها بيانات سوق عمل |

### Example Response

```json
{
  "success": true,
  "message": "Market analysis career paths retrieved successfully.",
  "data": {
    "total": 3,
    "career_paths": [
      {
        "id": 14,
        "name": "Backend Developer",
        "description": "Backend development track focused on server-side applications, APIs, databases, and backend engineering practices.",
        "total_job_postings": 5,
        "latest_snapshot_date": "2026-07-24",
        "has_market_data": true
      }
    ]
  }
}
```

### Frontend Notes

لا تعتمد على رقم ID ثابت، لأن ID قد يختلف بين جهاز وآخر.  
اعرض اسم المسار، عدد الإعلانات، وتاريخ آخر تحليل.

---


---

## 3.1.1 How To Get careerPathId

بعض endpoints تحتاج قيمة:

```http
{careerPathId}
```

مثل:

```http
GET /api/market-analysis/career-paths/{careerPathId}/skill-demand
GET /api/market-analysis/career-paths/{careerPathId}/trends
GET /api/market-analysis/career-paths/{careerPathId}/skills/{skillId}/evidence
```

هذه القيمة لا يتم كتابتها بشكل ثابت في الواجهة.

يجب على الفرونت أولاً استدعاء endpoint قائمة المسارات:

```http
GET /api/market-analysis/career-paths?only_with_market_data=1
```

ثم يأخذ قيمة `id` من المسار الذي اختاره المستخدم.

### Example

Response من قائمة المسارات:

```json
{
  "success": true,
  "message": "Market analysis career paths retrieved successfully.",
  "data": {
    "total": 3,
    "career_paths": [
      {
        "id": 14,
        "name": "Backend Developer",
        "description": "Backend development track focused on server-side applications, APIs, databases, and backend engineering practices.",
        "total_job_postings": 5,
        "latest_snapshot_date": "2026-07-24",
        "has_market_data": true
      },
      {
        "id": 21,
        "name": "Frontend Developer",
        "description": "Frontend development track.",
        "total_job_postings": 10,
        "latest_snapshot_date": "2026-07-24",
        "has_market_data": true
      }
    ]
  }
}
```

إذا اختار المستخدم مسار:

```text
Backend Developer
```

فالفرونت يأخذ:

```json
"id": 14
```

ثم يستخدمه في الراوت التالي:

```http
GET /api/market-analysis/career-paths/14/skill-demand
```

### Frontend Flow

```text
1. Call:
   GET /api/market-analysis/career-paths?only_with_market_data=1

2. Display career paths to the user:
   Backend Developer
   Frontend Developer
   Mobile Developer

3. User selects one career path.

4. Frontend reads selectedCareerPath.id.

5. Frontend calls:
   GET /api/market-analysis/career-paths/{selectedCareerPath.id}/skill-demand
```

### Important Note

لا تعتمد على أرقام ثابتة مثل:

```text
14
21
28
```

لأن هذه الأرقام قد تختلف بين بيئة وأخرى حسب ترتيب البيانات في قاعدة البيانات.

الطريقة الصحيحة دائماً هي أخذ `careerPathId` من endpoint:

```http
GET /api/market-analysis/career-paths?only_with_market_data=1
```

## 3.2 Get Skill Demand For Career Path

### Endpoint

```http
GET /api/market-analysis/career-paths/{careerPathId}/skill-demand
```

### Purpose

يعرض المهارات الأكثر طلباً ضمن مسار معين، مع نسبة ظهور كل مهارة في إعلانات الوظائف.

### Example

```http
GET /api/market-analysis/career-paths/14/skill-demand
```

### Example Response

```json
{
  "success": true,
  "message": "Market skill demand insights retrieved successfully.",
  "data": {
    "career_path": {
      "id": 14,
      "name": "Backend Developer"
    },
    "total_job_postings": 5,
    "skills": [
      {
        "skill_id": 57,
        "skill_name": "Python",
        "skill_category": "Programming Language",
        "job_posting_count": 5,
        "demand_percentage": 100,
        "demand_level": "core"
      },
      {
        "skill_id": 58,
        "skill_name": "Flask",
        "skill_category": "Framework",
        "job_posting_count": 4,
        "demand_percentage": 80,
        "demand_level": "core"
      }
    ],
    "skill_map": {
      "Programming Language": [
        {
          "skill_id": 57,
          "skill_name": "Python",
          "job_posting_count": 5,
          "demand_percentage": 100,
          "demand_level": "core"
        }
      ]
    }
  }
}
```

### Field Explanation

| Field | Meaning |
|---|---|
| total_job_postings | عدد إعلانات الوظائف التي تم تحليلها لهذا المسار |
| job_posting_count | عدد الإعلانات التي ظهرت فيها المهارة |
| demand_percentage | نسبة ظهور المهارة من إجمالي الإعلانات |
| demand_level | مستوى أهمية المهارة في السوق |
| skill_map | نفس المهارات مجمعة حسب التصنيف |

### Demand Level Meaning

| Value | Arabic Meaning | Description |
|---|---|---|
| core | مهارة أساسية | مطلوبة في نسبة عالية من الوظائف |
| important | مهارة مهمة | مطلوبة بشكل جيد |
| supporting | مهارة داعمة | تظهر في بعض الوظائف فقط |

---


### Note About Missing Skills

هذا endpoint يعرض فقط المهارات التي تم اكتشافها فعلياً داخل إعلانات الوظائف.

إذا لم تظهر مهارة معينة في تحليل سوق العمل، فلن تظهر داخل مصفوفة:

```json
"skills": []
```

هذا لا يعني أن المهارة غير موجودة في النظام، بل يعني فقط أنها لم تظهر في بيانات السوق الحالية لهذا المسار.

## 3.3 Get Market Trends

### Endpoint

```http
GET /api/market-analysis/career-paths/{careerPathId}/trends
```

### Purpose

يعرض آخر snapshot محفوظة للطلب على المهارات.

### Optional Date Filter

```http
GET /api/market-analysis/career-paths/{careerPathId}/trends?date=2026-07-24
```

### Example Response

```json
{
  "success": true,
  "message": "Market trend snapshot retrieved successfully.",
  "data": {
    "career_path": {
      "id": 14,
      "name": "Backend Developer"
    },
    "analyzed_date": "2026-07-24",
    "total_skills": 8,
    "trends": [
      {
        "skill_id": 57,
        "skill_name": "Python",
        "skill_category": "Programming Language",
        "demand_score": 100,
        "trend_direction": "new",
        "source_job_count": 5,
        "analyzed_date": "2026-07-24"
      }
    ]
  }
}
```

### Trend Direction Meaning

| Value | Arabic Meaning | Description |
|---|---|---|
| new | بيانات جديدة | لا توجد مقارنة سابقة بعد |
| rising | الطلب يرتفع | الطلب زاد مقارنة بالتحليل السابق |
| stable | الطلب مستقر | لا يوجد تغير واضح |
| falling | الطلب ينخفض | الطلب انخفض مقارنة بالتحليل السابق |

---

## 3.4 Get Skill Evidence

### Endpoint

```http
GET /api/market-analysis/career-paths/{careerPathId}/skills/{skillId}/evidence
```

### Purpose

يعرض الأدلة التي تشرح لماذا تم احتساب مهارة معينة.  
يعني يعرض الإعلانات أو الجمل التي ظهرت فيها المهارة.

### Example

```http
GET /api/market-analysis/career-paths/14/skills/57/evidence?limit=10
```

### Example Response

```json
{
  "success": true,
  "message": "Skill evidence retrieved successfully.",
  "data": {
    "career_path": {
      "id": 14,
      "name": "Backend Developer"
    },
    "skill_id": 57,
    "total_returned": 1,
    "evidence": [
      {
        "job_posting": {
          "id": 1,
          "title": "Python Flask Backend Developer",
          "company_name": "Backend Alpha",
          "location": "Remote",
          "language": "en",
          "source_name": "backend_test_dataset"
        },
        "skill": {
          "id": 57,
          "name": "Python",
          "category": "Programming Language"
        },
        "evidence": {
          "matched_text": "Python",
          "matched_language": "en",
          "alias": "Python",
          "confidence": 1,
          "extraction_method": "alias_match",
          "context": "We are looking for a backend developer with Python, Flask, SQL, REST API, Git and Testing experience"
        }
      }
    ]
  }
}
```

### Frontend Notes

استخدم هذا endpoint عند الضغط على مهارة معينة لعرض سبب ظهورها في تحليل السوق.

مثال عرض للطالب أو المشرف:

> ظهرت مهارة Python في إعلان وظيفة ضمن الجملة التالية:  
> We are looking for a backend developer with Python, Flask...

---

# 4. Recommendation Endpoints With Market Context

---

## 4.1 Skill Gaps With Market Context

### Endpoint

```http
GET /api/assessments/{session}/skill-gaps
```

### Purpose

يعرض فجوات الطالب بين مستواه الحالي والمستوى المطلوب، مع معلومات سوق العمل لكل مهارة.

### Example Response

```json
{
  "success": true,
  "message": "Skill gaps calculated successfully.",
  "data": {
    "assessment_session_id": 3,
    "gaps": [
      {
        "skill_id": 57,
        "skill_name": "Python",
        "required_level": 3,
        "actual_level": 2.9,
        "gap": 0.1,
        "priority": "low",
        "status": "needs_improvement",
        "market": {
          "available": true,
          "demand_score": 100,
          "demand_level": "core",
          "trend_direction": "new",
          "source_job_count": 5,
          "sample_size": 5,
          "analyzed_date": "2026-07-24",
          "matched_by": "skill_id",
          "matched_market_skill_id": 57,
          "student_message": "مهارة Python مطلوبة في سوق العمل لمسار Backend Developer، حيث ظهرت في 5 من أصل 5 إعلانات وظائف تم تحليلها بنسبة طلب 100%. لذلك تعتبر من المهارات الأساسية التي يُنصح بإعطائها أولوية عالية في خطة التعلم.",
          "labels": {
            "demand_level": "مهارة أساسية",
            "trend_direction": "بيانات جديدة",
            "learning_priority": "أولوية عالية"
          },
          "explanations": {
            "demand_score": "تعني أن هذه المهارة ظهرت في 100% من إعلانات الوظائف التي تم تحليلها لهذا المسار.",
            "source_job_count": "تعني أن هذه المهارة ظهرت في 5 من أصل 5 إعلانات وظائف تم تحليلها.",
            "demand_level": "تعني أن هذه المهارة مطلوبة في نسبة عالية من الوظائف، لذلك تعتبر أساسية لهذا المسار.",
            "trend_direction": "تعني أن هذه أول نتيجة تحليل متوفرة لهذه المهارة، لذلك لا توجد مقارنة سابقة بعد."
          }
        }
      }
    ]
  }
}
```

### Important Fields For UI

| Field | UI Usage |
|---|---|
| market.available | إذا true اعرض معلومات السوق |
| market.student_message | أفضل نص مباشر للطالب |
| market.labels.demand_level | مثال: مهارة أساسية |
| market.labels.learning_priority | مثال: أولوية عالية |
| market.explanations | شرح تفصيلي عند الضغط على "ماذا يعني هذا؟" |
| market.source_job_count | عدد الإعلانات التي ظهرت فيها المهارة |
| market.sample_size | إجمالي الإعلانات المحللة |
| market.matched_by | Debug فقط غالباً |
| market.matched_market_skill_id | Debug فقط غالباً |

### When market.available = false

```json
{
  "available": false,
  "student_message": "لا توجد حالياً بيانات سوق عمل كافية لهذه المهارة ضمن مسار Backend Developer. لذلك سيتم تحديد أولوية التعلم بناءً على نتيجة تقييمك فقط."
}
```

في هذه الحالة اعرض `student_message` فقط، ولا تعرض demand score.

---

---

## 4.1.1 What Happens If A Skill Has No Market Data?

قد تكون المهارة موجودة ضمن تقييم الطالب أو ضمن Career Path Skills، لكنها غير موجودة حالياً في تحليل سوق العمل.

مثال:

الطالب لديه مهارة:

```text
GraphQL
```

لكن تحليل سوق العمل الحالي لهذا المسار لم يجد مهارة GraphQL ضمن إعلانات الوظائف التي تم تحليلها.

في هذه الحالة لا يفشل الـ API، ولا يتم حذف المهارة من فجوات الطالب.

بدلاً من ذلك يرجع الحقل `market` بهذا الشكل:

```json
{
  "available": false,
  "student_message": "لا توجد حالياً بيانات سوق عمل كافية لهذه المهارة ضمن مسار Backend Developer. لذلك سيتم تحديد أولوية التعلم بناءً على نتيجة تقييمك فقط.",
  "labels": {
    "demand_level": "غير متوفر",
    "trend_direction": "غير متوفر",
    "learning_priority": "تعتمد على نتيجة التقييم"
  },
  "explanations": {
    "market_data": "لم يتم العثور على تحليل سوق عمل حديث لهذه المهارة ضمن هذا المسار."
  }
}
```

### Frontend Behavior

إذا كانت:

```json
"market": {
  "available": false
}
```

فالواجهة يجب أن:

- تعرض `market.student_message`.
- لا تعرض `demand_score`.
- لا تعرض `source_job_count`.
- لا تعرض badges مثل "مهارة أساسية" أو "أولوية عالية".
- تعتمد على `gap`, `priority`, و `assessment_reliability` لعرض أولوية التعلم.

### Example UI Message

```text
لا توجد حالياً بيانات سوق عمل كافية لهذه المهارة ضمن هذا المسار.
سيتم تحديد أولوية التعلم بناءً على نتيجة تقييمك فقط.
```

### Important Difference Between Endpoints

في endpoint التالي:

```http
GET /api/market-analysis/career-paths/{careerPathId}/skill-demand
```

المهارات غير الموجودة في تحليل سوق العمل لا تظهر ضمن قائمة `skills`.

مثلاً إذا لم تظهر GraphQL في أي إعلان وظيفة، فلن ترجع داخل:

```json
"skills": []
```

أما في endpoint التالي:

```http
GET /api/assessments/{session}/skill-gaps
```

فالمهارة تظهر لأنها جزء من تقييم الطالب، لكن يكون:

```json
"market": {
  "available": false
}
```

السبب أن `skill-demand` يعرض فقط المهارات المكتشفة في السوق، بينما `skill-gaps` يعرض كل مهارات الطالب المطلوبة، ثم يضيف معلومات السوق إن وجدت.

### Summary

| Endpoint | If skill has no market data |
|---|---|
| `/market-analysis/career-paths/{careerPathId}/skill-demand` | لا تظهر المهارة ضمن `skills` |
| `/assessments/{session}/skill-gaps` | تظهر المهارة مع `market.available = false` |
| `/assessments/{session}/learning-path` | تظهر المهارة إذا كان لديها gap، ومعها `market.available = false` |

## 4.2 Learning Path With Market Context

### Endpoint

```http
GET /api/assessments/{session}/learning-path
```

### Purpose

يعرض خطة التعلم الخاصة بالطالب، مع الموارد التعليمية ومعلومات سوق العمل.

### Example Response

```json
{
  "message": "Learning path generated",
  "data": [
    {
      "skill_id": 58,
      "skill_name": "Flask",
      "current_level": 1.9,
      "target_level": 2.5,
      "priority": "low",
      "market": {
        "available": true,
        "demand_score": 80,
        "demand_level": "core",
        "student_message": "مهارة Flask مطلوبة في سوق العمل لمسار Backend Developer، حيث ظهرت في 4 من أصل 5 إعلانات وظائف تم تحليلها بنسبة طلب 80%. لذلك تعتبر من المهارات الأساسية التي يُنصح بإعطائها أولوية عالية في خطة التعلم."
      },
      "resources": []
    }
  ]
}
```

### Sorting Logic

ترتيب خطة التعلم يعتمد على:

1. حجم الفجوة بين مستوى الطالب والمستوى المطلوب.
2. طلب سوق العمل على المهارة كعامل ترجيح إضافي.

يعني إذا كانت مهارتان لديهما فجوة متقاربة، المهارة المطلوبة أكثر في السوق تحصل على أولوية أعلى.

---

# 5. Recommended Frontend Display

## Skill Gap Card Example

```text
Python
مستواك الحالي: 2.9
المستوى المطلوب: 3
الفجوة: 0.1
الحالة: تحتاج تحسين بسيط

سوق العمل:
مهارة أساسية - أولوية عالية

مهارة Python مطلوبة في سوق العمل لمسار Backend Developer، حيث ظهرت في 5 من أصل 5 إعلانات وظائف تم تحليلها بنسبة طلب 100%.
```

## Suggested Badges

| Demand Level | Suggested Label |
|---|---|
| core | مهارة أساسية |
| important | مهارة مهمة |
| supporting | مهارة داعمة |
| unavailable | غير متوفر |

---

# 6. Demo Command

لتجهيز بيانات Demo كاملة:

```bash
php artisan market:seed-demo-data --fresh
```

هذا الأمر يقوم بـ:

- تشغيل seeders الخاصة بالقاموس
- استيراد demo job postings
- إعادة تحليل الإعلانات
- إنشاء trend snapshots
- حذف بيانات demo القديمة عند استخدام fresh

## Supported Demo Career Paths

```text
Backend Developer
Frontend Developer
Mobile Developer
```

---

# 7. Current MVP Status

## Completed

- Market job postings storage
- Skill extraction using aliases
- Skill occurrence evidence
- Demand score calculation
- Trend snapshots
- Career path skill maps
- Market analysis APIs
- Skill gaps integration
- Learning path integration
- Student-friendly market messages
- Normalized-name fallback
- Demo seed command
- Regression tests

## Future Enhancements

- Connect to a real jobs API
- Add scheduled weekly market analysis
- Add admin dashboard for data quality
- Analyze salaries and locations
- Improve NLP beyond alias matching
- Add larger real dataset

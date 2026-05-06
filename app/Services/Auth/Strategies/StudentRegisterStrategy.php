<?php
namespace App\Services\Auth\Strategies;

use App\Events\UserRegistered;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\StudentRepositoryInterface;
use App\Services\ImageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentRegisterStrategy implements RegisterStrategyInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepo,
        private StudentRepositoryInterface $studentRepo,
        private ImageService $imageService

    ) {}

    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
                    
//             $imagePath = null;
// if (!empty($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
//     $imagePath = $this->imageService->uploadImage(
//         $data['profile_picture'],
//         'profiles'
//     );


            $user = $this->userRepo->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                // 'bio' => $data['bio'] ?? null,
                // 'profile_picture_url' => $imagePath,
            ]);

            $user->assignRole('student');

            $student = $this->studentRepo->create([
                'user_id' => $user->id,
                // 'university' => $data['university'],
                // 'major' => $data['major'],
                // 'graduation_year' => $data['graduation_year'],
                // 'phone' => $data['phone'] ?? null,
            ]);

            $token = $user->createToken('api-token')->plainTextToken;
         
            DB::afterCommit(function () use ($user, $student) {
    
            event(new UserRegistered(
                user: $user,
                profile: $student,
                role: 'student'
            ));
            
        });

            return [
                'user' => $user,
                'profile' => $student,
                'token' => $token,
            ];
        });
    }
}
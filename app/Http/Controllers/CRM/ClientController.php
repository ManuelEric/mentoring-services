<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\CRM\Alumni;
use App\Models\CRM\Client;
use App\Models\CRM\Editor;
use App\Models\CRM\Mentor;
use App\Models\Education;
use App\Models\Roles;
use App\Models\Students;
use App\Models\User;
use App\Models\UserRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Faker\Generator as Faker;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientController extends Controller
{
    
    public function synchronize($role, $type)
    {
        $request = [
            'role' => $role,
            'type' => $type
        ];

        $rules = [
            'role' => 'required|in:student,mentor,editor,alumni',
            'type' => 'required|in:sync,import'
        ];

        $validator = Validator::make($request, $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => $validator->errors()], 400);
        }

        try {
            switch (strtolower($role)) {
                case "student": 
                    $data = $type == "sync" ? $this->recap_student(false, "yes") : $this->import_student();
                    break;
                
                case "mentor":
                    $data = $type == "sync" ? $this->recap_mentor(false, "yes") : $this->import_mentor();
                    break;

                case "editor":
                    $data = $type == "sync" ? $this->recap_editor(false, "yes") : $this->import_editor();
                    break;

                case "alumni":
                    $data = $type == "sync" ? $this->recap_alumni(false, "yes") : $this->import_alumni();
                    break;
            }
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function recap_alumni($isNull = true, $paginate = "no")
    {  
        $alumnis = $educations = array();
        $alumni = Alumni::with('student', 'student.school')->
        when(!$isNull, function ($query) {
                $query->whereHas('student', function($q1) {
                    $q1->where('st_firstname', '!=', '')->where('st_lastname', '!=', '')->where('st_mail', '!=', '');
                });
            })->distinct()->get();
        foreach ($alumni as $data) {
            $find = User::where('email', $data->st_mail)->count();
            if ($find == 0) {

                $alumnis[] = array(
                    'first_name' => $this->remove_blank($data->student->st_firstname),
                    'last_name' => $this->remove_blank($data->student->st_lastname),
                    'phone_number' => $this->remove_blank($data->student->st_phone),
                    'email' => $this->remove_blank($data->student->st_mail),
                    'email_verified_at' => $data->student->st_mail === '' ? null : Carbon::now(),
                    'password' => $this->remove_blank($data->student->st_password),
                    'status' => 1,
                    'is_verified' => $data->student->st_mail === '' ? 0 : 1,
                    'remember_token' => null,
                    'profile_picture' => null,
                    'imported_id' => $this->remove_blank($data->student->st_id),
                    'position' => null,
                    'imported_from' => 'u5794939_allin_bd',
                    'educations' => array(
                        'graduated_from' => $data->student->school->sch_name,
                        'major' => null,
                        'degree' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'graduation_date' => $data->alu_graduatedate
                    )
                );
            }
        }

        return $paginate == "yes" ? $this->paginate($alumnis) : $alumnis;
    }

    public function import_alumni()
    {
        $bulk_data = $this->recap_alumni(false);
        DB::beginTransaction();
        try {
            foreach ($bulk_data as $alumni_data) {
                $alumni = new User;
                $alumni->first_name = $alumni_data['first_name'];
                $alumni->last_name = $alumni_data['last_name'];
                $alumni->phone_number = $alumni_data['phone_number'];
                $alumni->email = $alumni_data['email'];
                $alumni->email_verified_at = $alumni_data['email_verified_at'];
                $alumni->password = $alumni_data['password'];
                $alumni->status = $alumni_data['status'];
                $alumni->is_verified = $alumni_data['is_verified'];
                $alumni->remember_token = $alumni_data['remember_token'];
                $alumni->profile_picture = $alumni_data['profile_picture'];
                $alumni->imported_id = $alumni_data['imported_id'];
                $alumni->position = $alumni_data['position'];
                $alumni->imported_from = $alumni_data['imported_from'];
                $alumni->save();

                Education::insert(
                    ['user_id' => $alumni->id] + $alumni_data['educations']
                );

                $alumni->roles()->attach($alumni->id, ['role_id' => 4, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Import Data Student Issue : '.$e->getMessage());
            throw New Exception('Failed to import students data');
        }

        return $bulk_data;
    }

    public function recap_editor($isNull = true, $paginate = "no")
    {
        $editors = array();
        $editor_crm = Editor::where('status', 1)->get();
        foreach ($editor_crm as $data) {
            $find = User::where('email', $data->email)->first();

            if ($find) { //if email sudah ada di database
                
                $editors[] = array(
                    'first_name' => $this->remove_blank($data->first_name),
                    'last_name' => $this->remove_blank($data->last_name),
                    'phone_number' => $this->remove_blank($data->phone),
                    'email' => $this->remove_blank($data->email),
                    'email_verified_at' => $data->email === '' ? null : Carbon::now(),
                    'password' => $this->remove_blank($data->password),
                    'status' => $data->status,
                    'is_verified' => $data->email === '' ? 0 : 1,
                    'remember_token' => null,
                    'profile_picture' => null,
                    'imported_id' => null,
                    'position' => null,
                    'imported_from' => 'u5794939_editing',
                    'educations' => array(
                        'graduated_from' => $data->graduated_from,
                        'major' => $data->major,
                        'degree' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'graduation_date' => null
                    )
                );
            }
            
        }

        return $paginate == "yes" ? $this->paginate($editors) : $editors;
    }

    public function import_editor()
    {
        $bulk_data = $this->recap_mentor(false);
        DB::beginTransaction();
        try {
            foreach ($bulk_data as $editor_data) {

                if ($user = User::where('email', $editor_data['email'])->first()) {
                    $id = $user->id;
                    if (!UserRoles::where('user_id', $id)->where('role_id', 3)) {
                        $user->roles()->attach($id, ['role_id' => 3, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                    }
                    break;
                }

                $editor = User::insertOrIgnore([
                    'first_name' => $editor_data['first_name'],
                    'last_name' => $editor_data['last_name'],
                    'phone_number' => $editor_data['phone_number'],
                    'email' => $editor_data['email'],
                    'email_verified_at' => $editor_data['email_verified_at'],
                    'password' => $editor_data['password'],
                    'status' => $editor_data['status'],
                    'is_verified' => $editor_data['is_verified'],
                    'remember_token' => $editor_data['remember_token'],
                    'profile_picture' => $editor_data['profile_picture'],
                    'imported_id' => $editor_data['imported_id'],
                    'position' => $editor_data['position'],
                    'imported_from' => $editor_data['imported_from']
                ]);

                Education::insert(
                    ['user_id' => $editor->id] + $editor_data['educations']
                );

                $editor->roles()->attach($editor->id, ['role_id' => 3, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);

            }
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Import Data Mentor Issue : '.$e->getMessage());
            throw New Exception('Failed to import mentor data');
        }

        return $bulk_data;
    }

    public function recap_mentor($isNull = true, $paginate = "no")
    {
        $mentors = array();
        $mentor_crm = Mentor::with('university')->get();

        foreach ($mentor_crm as $data) {
            $find = User::where('imported_id', $data->mt_id)->first();
            if ($find) {

                $mentors[] = array(
                    'first_name' => $this->remove_blank($data->mt_firstn),
                    'last_name' => $this->remove_blank($data->mt_lastn),
                    'phone_number' => $this->remove_blank($data->mt_phone),
                    'email' => $this->remove_blank($data->mt_email),
                    'email_verified_at' => $data->mt_mail === '' ? null : Carbon::now(),
                    'password' => $this->remove_blank($data->mt_password),
                    'status' => 1,
                    'is_verified' => $data->mt_mail === '' ? 0 : 1,
                    'remember_token' => null,
                    'profile_picture' => null,
                    'imported_id' => $this->remove_blank($data->mt_id),
                    'position' => null,
                    'imported_from' => 'u5794939_allin_bd',
                    'educations' => array(
                        'graduated_from' => $data->university->univ_name,
                        'major' => $data->major,
                        'degree' => null,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'graduation_date' => null
                    )
                );
            }
        }

        return $paginate == "yes" ? $this->paginate($mentors) : $mentors;
    }

    public function import_mentor()
    {
        $bulk_data = $this->recap_mentor(false);
        DB::beginTransaction();
        try {
            foreach ($bulk_data as $mentor_data) {

                if ($user = User::where('email', $mentor_data['email'])->first()) {
                    $id = $user->id;
                    if (!UserRoles::where('user_id', $id)->where('role_id', 2)) {
                        $user->roles()->attach($id, ['role_id' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
                    }
                    break;
                }

                $mentor = new User;
                $mentor->first_name = $mentor_data['first_name'];
                $mentor->last_name = $mentor_data['last_name'];
                $mentor->phone_number = $mentor_data['phone_number'];
                $mentor->email = $mentor_data['email'];
                $mentor->email_verified_at = $mentor_data['email_verified_at'];
                $mentor->password = $mentor_data['password'];
                $mentor->status = $mentor_data['status'];
                $mentor->is_verified = $mentor_data['is_verified'];
                $mentor->remember_token = $mentor_data['remember_token'];
                $mentor->profile_picture = $mentor_data['profile_picture'];
                $mentor->imported_id = $mentor_data['imported_id'];
                $mentor->position = $mentor_data['position'];
                $mentor->imported_from = $mentor_data['imported_from'];
                $mentor->save();

                Education::insert(
                    ['user_id' => $mentor->id] + $mentor_data['educations']
                );

                $mentor->roles()->attach($mentor->id, ['role_id' => 2, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Import Data Mentor Issue : '.$e->getMessage());
            throw New Exception('Failed to import mentor data');
        }

        return $bulk_data;
    }

    public function recap_student($isNull = true, $paginate = "no")
    {
        $students = array();
        $alumni = Alumni::select('st_id')->get();
        $client = Client::whereHas('programs', function($query) {
            $query->where('prog_main', 'Admissions Mentoring')->where('stprog_status', 1);
        })->whereNotIn('st_id', $alumni)->when(!$isNull, function ($query) {
                $query->where(function($q1) {
                    $q1->where('st_firstname', '!=', '')->where('st_lastname', '!=', '')->where('st_mail', '!=', '');
                });
        })->distinct()->get();

        foreach ($client->unique('st_mail') as $client_data) {
            $find = Students::where('email', $client_data->st_mail)->count();
            if ($find == 0) { // if there are no data with client data email then save record
                $students[] = array(
                    'first_name' => $client_data->st_firstname,
                    'last_name' => $client_data->st_lastname,
                    'birthday' => $this->remove_invalid_date($client_data->st_dob),
                    'phone_number' => $client_data->st_phone,
                    'grade' => isset($client_data->school->sch_level) ? $this->remove_string_grade($client_data->school->sch_level) : null,
                    'email' => $this->remove_blank($client_data->st_mail),
                    'email_verified_at' => $client_data->st_mail != null ? Carbon::now() : null,
                    'address' => $this->remove_blank($client_data->st_address, 'text'),
                    'city' => $this->remove_blank($client_data->st_city),
                    'password' => $this->remove_blank($client_data->st_password),
                    'imported_from' => 'u5794939_allin_bd',
                    'imported_id' => $this->remove_blank($client_data->st_id, 'text'),
                    'status' => 1,
                    'is_verified' => $client_data->st_mail == '' ? 0 : 1,
                    'school_name' => isset($client_data->school->sch_name) ? ($client_data->school->sch_name == '-' ? null : $client_data->school->sch_name) : null
                );
            }
        }

        return $paginate == "yes" ? $this->paginate($students) : $students;
    }

    public function import_student()
    {
        $students = $this->recap_student(false);

        DB::beginTransaction();
        try {
            Students::insert($students);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Import Data Student Issue : '.$e->getMessage());
            throw New Exception('Failed to import students data');
        }

        return 'Students data has been imported';
    }

    //** HELPER */

    public function existing_check ($email, $role)
    {

    }

    public function paginate($items, $perPage = 10, $page = null, $options = [])
    {
        $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
        $items = $items instanceof Collection ? $items : Collection::make($items);
        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }

    public function remove_invalid_date($data)
    {
        if ($data) {
            $date = explode('-', $data);
            $month = $date[1];
            $day = $date[2];
            $year = $date[0];
    
            return checkdate($month, $day, $year) ? $data : null;
        }
    }

    public function remove_blank($data)
    {
        return (empty($data) || ($data == '-')) ? null : $data;
    }

    public function remove_string_grade($grade)
    {
        $output = preg_replace( '/[^0-9]/', '', $grade );
        return $output;
    }
}

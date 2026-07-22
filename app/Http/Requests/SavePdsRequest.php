<?php

namespace App\Http\Requests;

use App\Models\Pds;
use Illuminate\Foundation\Http\FormRequest;

class SavePdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pds = $this->route('pds');

        return $pds instanceof Pds
            && $this->user()
            && $pds->canBeEditedBy($this->user());
    }

    protected function prepareForValidation(): void
    {
        $personalInfo = $this->input('personal_info', []);

        if (is_array($personalInfo) && filled($personalInfo['mobile_no'] ?? null)) {
            $personalInfo['mobile_no'] = preg_replace('/\D+/', '', (string) $personalInfo['mobile_no']);
        }

        foreach (['citizenship_filipino', 'citizenship_dual'] as $field) {
            if (array_key_exists($field, $personalInfo)) {
                $personalInfo[$field] = filter_var(
                    $personalInfo[$field],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                );
            }
        }

        $this->merge(['personal_info' => $personalInfo]);
    }

    public function rules(): array
    {
        return [
            'personal_info' => ['required', 'array'],
            'personal_info.surname' => ['required', 'string', 'max:255'],
            'personal_info.first_name' => ['required', 'string', 'max:255'],
            'personal_info.middle_name' => ['nullable', 'string', 'max:255'],
            'personal_info.name_ext' => ['nullable', 'string', 'max:255'],
            'personal_info.date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'personal_info.place_of_birth' => ['nullable', 'string', 'max:255'],
            'personal_info.sex_at_birth' => ['nullable', 'string', 'max:255'],
            'personal_info.civil_status' => ['nullable', 'string', 'max:255'],
            'personal_info.citizenship_type' => ['required', 'in:Filipino,Dual'],
            'personal_info.citizenship_filipino' => ['nullable', 'boolean'],
            'personal_info.citizenship_dual' => ['nullable', 'boolean'],
            'personal_info.citizenship_dual_type' => ['nullable', 'required_if:personal_info.citizenship_type,Dual', 'string', 'max:255'],
            'personal_info.citizenship_dual_country' => ['nullable', 'required_if:personal_info.citizenship_type,Dual', 'string', 'max:255'],
            'personal_info.height' => ['nullable', 'string', 'max:255'],
            'personal_info.weight' => ['nullable', 'string', 'max:255'],
            'personal_info.blood_type' => ['nullable', 'string', 'max:255'],
            'personal_info.umid_id_no' => ['nullable', 'string', 'max:255'],
            'personal_info.pagibig_id_no' => ['nullable', 'string', 'max:255'],
            'personal_info.philhealth_no' => ['nullable', 'string', 'max:255'],
            'personal_info.philsys_no' => ['nullable', 'string', 'max:255'],
            'personal_info.tin_no' => ['nullable', 'string', 'max:255'],
            'personal_info.agency_employee_no' => ['nullable', 'string', 'max:255'],
            'personal_info.telephone_no' => ['nullable', 'string', 'max:255'],
            'personal_info.mobile_no' => ['required', 'regex:/^[0-9]{10,15}$/'],
            'personal_info.email_address' => ['nullable', 'email:rfc', 'max:255'],
            'personal_info.residential_house' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_street' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_subdivision' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_barangay' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_city' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_province' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_region' => ['nullable', 'string', 'max:255'],
            'personal_info.residential_zip_code' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_house' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_street' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_subdivision' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_barangay' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_city' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_province' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_region' => ['nullable', 'string', 'max:255'],
            'personal_info.permanent_zip_code' => ['nullable', 'string', 'max:255'],

            'family_background' => ['present', 'array'],
            'family_background.spouse_surname' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_first_name' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_middle_name' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_name_ext' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_occupation' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_employer' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_business_address' => ['nullable', 'string', 'max:255'],
            'family_background.spouse_telephone_no' => ['nullable', 'string', 'max:255'],
            'family_background.father_surname' => ['nullable', 'string', 'max:255'],
            'family_background.father_first_name' => ['nullable', 'string', 'max:255'],
            'family_background.father_middle_name' => ['nullable', 'string', 'max:255'],
            'family_background.father_name_ext' => ['nullable', 'string', 'max:255'],
            'family_background.mother_maiden_surname' => ['nullable', 'string', 'max:255'],
            'family_background.mother_maiden_first_name' => ['nullable', 'string', 'max:255'],
            'family_background.mother_maiden_middle_name' => ['nullable', 'string', 'max:255'],

            'children' => ['present', 'array', 'max:100'],
            'children.*.child_name' => ['nullable', 'required_with:children.*.child_date_of_birth', 'string', 'max:255'],
            'children.*.child_date_of_birth' => ['nullable', 'date_format:Y-m-d'],

            'education' => ['present', 'array', 'max:30'],
            'education.*.level' => ['nullable', 'required_with:education.*.school_name,education.*.degree,education.*.from,education.*.to,education.*.highest_level,education.*.year_graduated,education.*.honors', 'string', 'max:255'],
            'education.*.school_name' => ['nullable', 'required_with:education.*.level,education.*.degree,education.*.from,education.*.to,education.*.highest_level,education.*.year_graduated,education.*.honors', 'string', 'max:255'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
            'education.*.from' => ['nullable', 'string', 'max:255'],
            'education.*.to' => ['nullable', 'string', 'max:255'],
            'education.*.highest_level' => ['nullable', 'string', 'max:255'],
            'education.*.year_graduated' => ['nullable', 'string', 'max:255'],
            'education.*.honors' => ['nullable', 'string', 'max:255'],

            'eligibility' => ['present', 'array', 'max:100'],
            'eligibility.*.eligibility' => ['nullable', 'string', 'max:255'],
            'eligibility.*.rating' => ['nullable', 'string', 'max:255'],
            'eligibility.*.exam_date' => ['nullable', 'date_format:Y-m-d'],
            'eligibility.*.place_taken' => ['nullable', 'string', 'max:255'],
            'eligibility.*.license_number' => ['nullable', 'string', 'max:255'],
            'eligibility.*.license_validity' => ['nullable', 'date_format:Y-m-d'],

            'work_experience' => ['present', 'array', 'max:200'],
            'work_experience.*.position' => ['nullable', 'required_with:work_experience.*.agency,work_experience.*.appointment_status,work_experience.*.government_service,work_experience.*.from_date,work_experience.*.to_date', 'string', 'max:255'],
            'work_experience.*.agency' => ['nullable', 'required_with:work_experience.*.position,work_experience.*.appointment_status,work_experience.*.government_service,work_experience.*.from_date,work_experience.*.to_date', 'string', 'max:255'],
            'work_experience.*.appointment_status' => ['nullable', 'string', 'max:255'],
            'work_experience.*.government_service' => ['nullable', 'string', 'max:255'],
            'work_experience.*.from_date' => ['nullable', 'date_format:Y-m-d'],
            'work_experience.*.to_date' => ['nullable', 'date_format:Y-m-d'],

            'voluntary_work' => ['present', 'array', 'max:100'],
            'voluntary_work.*.organization' => ['nullable', 'required_with:voluntary_work.*.nature_of_work,voluntary_work.*.from_date,voluntary_work.*.to_date,voluntary_work.*.hours', 'string', 'max:255'],
            'voluntary_work.*.nature_of_work' => ['nullable', 'string', 'max:255'],
            'voluntary_work.*.from_date' => ['nullable', 'date_format:Y-m-d'],
            'voluntary_work.*.to_date' => ['nullable', 'date_format:Y-m-d'],
            'voluntary_work.*.hours' => ['nullable', 'integer', 'min:0'],

            'trainings' => ['present', 'array', 'max:1000'],
            'trainings.*.training_title' => ['nullable', 'required_with:trainings.*.date_from,trainings.*.date_to,trainings.*.hours,trainings.*.training_type,trainings.*.conducted_by', 'string', 'max:255'],
            'trainings.*.date_from' => ['nullable', 'date_format:Y-m-d'],
            'trainings.*.date_to' => ['nullable', 'date_format:Y-m-d'],
            'trainings.*.hours' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'trainings.*.training_type' => ['nullable', 'string', 'max:255'],
            'trainings.*.conducted_by' => ['nullable', 'string', 'max:255'],

            'skills_hobbies' => ['present', 'array', 'max:100'],
            'skills_hobbies.*.skills_hobbies' => ['nullable', 'string', 'max:255'],
            'non_academic_recognition' => ['present', 'array', 'max:100'],
            'non_academic_recognition.*.recognition' => ['nullable', 'string', 'max:255'],
            'membership_organizations' => ['present', 'array', 'max:100'],
            'membership_organizations.*.organization_name' => ['nullable', 'string', 'max:255'],

            'questions' => ['present', 'array'],
            'questions.q34a_third_degree' => ['nullable', 'boolean'],
            'questions.q34b_fourth_degree' => ['nullable', 'boolean'],
            'questions.q35a_admin_offense' => ['nullable', 'boolean'],
            'questions.q35b_criminal_charge' => ['nullable', 'boolean'],
            'questions.q36_convicted' => ['nullable', 'boolean'],
            'questions.q37_separated_service' => ['nullable', 'boolean'],
            'questions.q38a_candidate' => ['nullable', 'boolean'],
            'questions.q38b_resigned_for_campaign' => ['nullable', 'boolean'],
            'questions.q39_immigrant' => ['nullable', 'boolean'],
            'questions.q40a_indigenous' => ['nullable', 'boolean'],
            'questions.q40b_pwd' => ['nullable', 'boolean'],
            'questions.q40c_solo_parent' => ['nullable', 'boolean'],
            'questions.q35b_date_filed' => ['nullable', 'date_format:Y-m-d'],
            'questions.q35b_status' => ['nullable', 'string', 'max:255'],
            'questions.q39_country' => ['nullable', 'string', 'max:255'],
            'questions.q40a_group' => ['nullable', 'string', 'max:255'],
            'questions.q40b_pwd_id' => ['nullable', 'string', 'max:255'],
            'questions.q40c_solo_parent_id' => ['nullable', 'string', 'max:255'],
            'questions.q34a_details' => ['nullable', 'string', 'max:10000'],
            'questions.q34b_details' => ['nullable', 'string', 'max:10000'],
            'questions.q35a_details' => ['nullable', 'string', 'max:10000'],
            'questions.q35b_details' => ['nullable', 'string', 'max:10000'],
            'questions.q36_details' => ['nullable', 'string', 'max:10000'],
            'questions.q37_details' => ['nullable', 'string', 'max:10000'],
            'questions.q38a_details' => ['nullable', 'string', 'max:10000'],
            'questions.q38b_details' => ['nullable', 'string', 'max:10000'],

            'references' => ['present', 'array', 'max:20'],
            'references.*.name' => ['nullable', 'string', 'max:255'],
            'references.*.office_address' => ['nullable', 'string', 'max:255'],
            'references.*.contact_no_email' => ['nullable', 'string', 'max:255'],

            'other_info' => ['present', 'array'],
            'other_info.government_id' => ['nullable', 'string', 'max:255'],
            'other_info.id_no' => ['nullable', 'string', 'max:255'],
            'other_info.date_place_issuance' => ['nullable', 'string', 'max:255'],
            'other_info.path_passport_photo' => ['nullable', 'string', 'max:2048'],

            'work_experience_sheets' => ['present', 'array', 'max:100'],
            'work_experience_sheets.*.from_date' => ['nullable', 'string', 'max:50'],
            'work_experience_sheets.*.to_date' => ['nullable', 'string', 'max:50'],
            'work_experience_sheets.*.position' => ['nullable', 'string', 'max:255'],
            'work_experience_sheets.*.office_unit' => ['nullable', 'string', 'max:255'],
            'work_experience_sheets.*.immediate_supervisor' => ['nullable', 'string', 'max:255'],
            'work_experience_sheets.*.agency_organization_location' => ['nullable', 'string', 'max:10000'],
            'work_experience_sheets.*.accomplishments' => ['present', 'array', 'max:100'],
            'work_experience_sheets.*.accomplishments.*' => ['nullable', 'string', 'max:5000'],
            'work_experience_sheets.*.summary_of_duties' => ['present', 'array', 'max:100'],
            'work_experience_sheets.*.summary_of_duties.*' => ['nullable', 'string', 'max:10000'],
            'work_experience_sheets.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'personal_info.date_of_birth' => 'date of birth',
            'personal_info.mobile_no' => 'mobile number',
            'personal_info.citizenship_type' => 'citizenship',
            'education.*.school_name' => 'school name',
            'work_experience.*.from_date' => 'work experience start date',
            'work_experience.*.to_date' => 'work experience end date',
            'trainings.*.training_title' => 'training title',
            'trainings.*.date_from' => 'training start date',
            'trainings.*.date_to' => 'training end date',
            'trainings.*.hours' => 'training hours',
        ];
    }

    public function messages(): array
    {
        return [
            'children.*.child_name.required_with' => 'Enter the child name when the birth date is provided.',
            'education.*.level.required_with' => 'Select the education level for this row.',
            'education.*.school_name.required_with' => 'Enter the school name for this education row.',
            'work_experience.*.position.required_with' => 'Enter the position for this work experience row.',
            'work_experience.*.agency.required_with' => 'Enter the agency or company for this work experience row.',
            'voluntary_work.*.organization.required_with' => 'Enter the organization for this voluntary work row.',
            'trainings.*.training_title.required_with' => 'Enter the title for this training row.',
            'trainings.*.hours.numeric' => 'Training hours must be a number.',
        ];
    }
}

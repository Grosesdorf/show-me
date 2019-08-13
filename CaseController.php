<?php

namespace App\Http\Controllers\Cases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cases\GetCaseRequest;
use App\Http\Requests\Cases\CreateCaseRequest;
use App\Http\Requests\Cases\UpdateCaseRequest;
use App\Models\CaseModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class CaseController extends Controller
{
    /**
     * Get all cases.
     *
     * @param GetCaseRequest $request
     *
     * @return JsonResponse
     */
    public function index(GetCaseRequest $request): JsonResponse
    {
        $user = Auth::user();
        $params = $request->only([
            'status_id',
            'name',
            'created_at',
            'started_at',
            'due_dated_at',
            'last_changed_user_id',
            'client_id',
            'guardian_id'
        ]);

        $query = CaseModel::filter($params);
        
        if (!$user->isSuperUser() || !$request->get('all_cases')) {
            $query = $query->byUserId($user->id, $request->get('roles'));
        }
        $cases = $query
            ->with(config('cases.user_relations_data'))
            ->addSelect(config('cases.fields_general'))
            ->latest('updated_at')
            ->paginate();

        $cases->each(function ($case) {
            $case->assignUserRoleToCase(Auth::id());
            $case->makeHidden(['files']);
            $case->makeVisible(['current_user_role', 'case_status_id']);
        });

        return Response::json($cases);
    }

    /**
     * Find case.
     *
     * @param int $case_id
     *
     * @return JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(int $case_id): JsonResponse
    {
        $case = CaseModel::query()
            ->with(config('cases.user_relations_data'))
            ->with('media')
            ->addSelect(config('cases.fields_all'))
            ->find($case_id);

        if (!$case) {
            return Response::jsonNotFound();
        }
        $case->append([
            'users',
            'default_assistant'
        ]);
        $case->makeVisible(CaseModel::RELATED_USERS_WITH_ROLE);
        $this->authorize('view', $case);

        return Response::json($case);
    }

    /**
     * Create case.
     *
     * @param CreateCaseRequest $request
     *
     * @return JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(CreateCaseRequest $request): JsonResponse
    {
        $this->authorize('create', CaseModel::class);

        $data = $request->all([
            'name',
            'office_location',
            'file_number',
            'venue',
            'clain_no',
            'doi',
            'ssn',
            'dirreff_attorney',
            'ciga_prof_ath_cases',
            'comp_file_type_and_number',
            'commenced_date',
            'description',
            'started_at',
            'due_dated_at',
            'client_id',
            'case_type_id',
            'case_status_id',
            'guardian_id',
            'employer_id',
            'employer_parent_id',
            'insurance_id',
            'files_ids',
            'applicant_id',
            'language_id',
            'age_on_date_of_injury',
            'claims_insurance_info',
            'claims_insurance_company_id',
            'policy_period_start',
            'policy_period_finish',
            'company_deductible_amount',
            'selfinsured_liability_amount',
            'explain',
            'notice_of_representation',
            'third_party_information',
            'attorney_ids',
            'attorney_132a_ids',
            'opened_at',
            'closed_at',
            'physical_file_scanned_at',
            'subbed_out',
            'subbed_out_at'
        ]);

        $case = new CaseModel($data);
        $case->user()->associate(Auth::id());
        $case->client()->associate($data['client_id']);
        $case->attachMedia($data['files_ids'], CaseModel::CASE_FILE);
        $case->caseType()->associate($data['case_type_id']);
        $case->caseStatus()->associate($data['case_status_id']);
        $case->employer()->associate($data['employer_id']);
        $case->employerParent()->associate($data['employer_parent_id']);
        $case->insurance()->associate($data['insurance_id']);
        $case->applicant()->associate($data['applicant_id']);
        $case->language()->associate($data['language_id']);
        $case->claimsInsuranceCompany()->associate($data['claims_insurance_company_id']);

        if ($data['guardian_id']) {
            $case->guardian()->associate($data['guardian_id']);
        }
        $case->save();

        $dataForLoad = array_merge(
            [
                'media'
            ],
            config('cases.user_relations_data')
        );
        $case->load($dataForLoad);
        $case->makeVisible(CaseModel::RELATED_USERS_WITH_ROLE);

        return Response::jsonSuccess('created_case', $case);
    }

    /**
     * Update case.
     *
     * @param int $case_id
     * @param UpdateCaseRequest $request
     *
     * @return JsonResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(int $case_id, UpdateCaseRequest $request): JsonResponse
    {
        $case = CaseModel::with(config('cases.user_relations_data'))
            ->find($case_id);

        if (!$case) {
            return Response::jsonNotFound();
        }

        $this->authorize('update', $case);

        $data = $request->all([
            'name',
            'office_location',
            'file_number',
            'venue',
            'clain_no',
            'doi',
            'ssn',
            'dirreff_attorney',
            'ciga_prof_ath_cases',
            'comp_file_type_and_number',
            'commanced_date',
            'description',
            'started_at',
            'due_dated_at',
            'client_id',
            'guardian_id',
            'employer_id',
            'employer_parent_id',
            'insurance_id',
            'files_ids',
            'case_status_id',
            'case_type_id',
            'applicant_id',
            'language_id',
            'age_on_date_of_injury',
            'claims_insurance_info',
            'claims_insurance_company_id',
            'policy_period_start',
            'policy_period_finish',
            'company_deductible_amount',
            'selfinsured_liability_amount',
            'explain',
            'notice_of_representation',
            'third_party_information',
        ]);

        $case->fill($data);
        $case->client()->associate($data['client_id']);

        if ($data['guardian_id']) {
            $case->guardian()->associate($data['guardian_id']);
        }

        $case->caseStatus()->associate($data['case_status_id']);
        $case->caseType()->associate($data['case_type_id']);
        $case->employer()->associate($data['employer_id']);
        $case->employerParent()->associate($data['employer_parent_id']);
        $case->insurance()->associate($data['insurance_id']);
        $case->applicant()->associate($data['applicant_id']);
        $case->language()->associate($data['language_id']);
        $case->save();
        $case->syncMedia($data['files_ids'], CaseModel::CASE_FILE);

        $dataForLoad = array_merge(
            [
                'media'
            ],
            config('cases.user_relations_data')
        );
        $case->load($dataForLoad);
        $case->makeVisible(CaseModel::RELATED_USERS_WITH_ROLE);

        return Response::jsonSuccess('updated_case', $case);
    }

    /**
     * Delete case.
     *
     * @param int $case_id
     *
     * @return JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(int $case_id): JsonResponse
    {
        $case = CaseModel::find($case_id);

        if (!$case) {
            return Response::jsonNotFound();
        }
        $this->authorize('delete', $case);
        $case->delete();

        return Response::jsonSuccess('deleted_case');
    }
}

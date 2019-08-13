<?php

namespace App\Http\Requests\Cases;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\CaseModel;

class GetCaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'status_id'    => 'nullable|exist:case_statuses,id',
            'roles'        => 'nullable|array',
            'roles.*'      => [
                Rule::in(CaseModel::USER_ROLES)
            ],
            'name'         => 'nullable|string|max:255',
            'created_at'   => 'nullable|date_format:U',
            'started_at'   => 'nullable|date_format:U',
            'due_dated_at' => 'nullable|date_format:U|after:started_at',
            'client_id'    => 'integer',
            'guardian_id'  => 'nullable|integer',
            'all_cases'    => 'nullable|sometimes|boolean'
        ];
    }
}

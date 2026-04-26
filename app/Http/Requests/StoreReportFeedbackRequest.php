<?php

namespace App\Http\Requests;

use App\Services\Analysis\ReportFeedbackService;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'report_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'chat_rating'   => ['nullable', 'integer', 'min:1', 'max:5'],

            'liked_tags'    => ['nullable', 'array'],
            'liked_tags.*'  => ['string', 'in:' . implode(',', ReportFeedbackService::LIKED_TAGS)],

            'disliked_tags'   => ['nullable', 'array'],
            'disliked_tags.*' => ['string', 'in:' . implode(',', ReportFeedbackService::DISLIKED_TAGS)],

            // Comment is required when rating is harshly critical so we have
            // actionable signal to fix the report; optional otherwise.
            'comment' => [
                $this->shouldRequireComment() ? 'required' : 'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => __('Please tell us briefly what went wrong — short ratings without context are hard to act on.'),
        ];
    }

    private function shouldRequireComment(): bool
    {
        $rating = (int) $this->input('report_rating', 0);
        return $rating > 0 && $rating <= 2;
    }
}

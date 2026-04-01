<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendCarouselRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:whatsapp_instances,id'],
            'phone' => ['required', 'string', 'min:10', 'max:40'],
            'message' => ['required', 'string', 'max:1024'],
            'carousel' => ['required', 'array', 'min:1'],
            'carousel.*.text' => ['required', 'string'],
            'carousel.*.image' => ['required', 'string'],
            'carousel.*.buttons' => ['nullable', 'array'],
            'carousel.*.buttons.*.label' => ['required_with:carousel.*.buttons', 'string'],
            'carousel.*.buttons.*.type' => ['required_with:carousel.*.buttons', 'string', 'in:URL,CALL,REPLY'],
            'carousel.*.buttons.*.url' => ['nullable', 'string', 'url'],
            'carousel.*.buttons.*.phone' => ['nullable', 'string'],
            'carousel.*.buttons.*.id' => ['nullable', 'string'],
            'delay_message' => ['nullable', 'integer', 'min:1', 'max:15'],
        ];
    }
}

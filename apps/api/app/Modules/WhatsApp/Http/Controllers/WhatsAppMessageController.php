<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Clients\DTOs\RemoveReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendAudioData;
use App\Modules\WhatsApp\Clients\DTOs\SendCarouselData;
use App\Modules\WhatsApp\Clients\DTOs\SendImageData;
use App\Modules\WhatsApp\Clients\DTOs\SendPixButtonData;
use App\Modules\WhatsApp\Clients\DTOs\SendReactionData;
use App\Modules\WhatsApp\Clients\DTOs\SendTextData;
use App\Modules\WhatsApp\Http\Requests\RemoveReactionRequest;
use App\Modules\WhatsApp\Http\Requests\SendAudioRequest;
use App\Modules\WhatsApp\Http\Requests\SendCarouselRequest;
use App\Modules\WhatsApp\Http\Requests\SendImageRequest;
use App\Modules\WhatsApp\Http\Requests\SendPixButtonRequest;
use App\Modules\WhatsApp\Http\Requests\SendReactionRequest;
use App\Modules\WhatsApp\Http\Requests\SendTextRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppMessageResource;
use App\Modules\WhatsApp\Models\WhatsAppInstance;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppMessagingService;
use App\Shared\Http\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppMessageController extends BaseController
{
    public function __construct(
        private readonly WhatsAppMessagingService $messagingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $messages = WhatsAppMessage::query()
            ->when($request->input('instance_id'), fn ($q, $v) => $q->where('instance_id', $v))
            ->when($request->input('direction'), fn ($q, $v) => $q->where('direction', $v))
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->with('chat')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated(WhatsAppMessageResource::collection($messages));
    }

    public function show(WhatsAppMessage $message): JsonResponse
    {
        return $this->success(new WhatsAppMessageResource($message->load('chat', 'instance')));
    }

    public function sendText(SendTextRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new SendTextData(
            phone: $request->validated('phone'),
            message: $request->validated('message'),
            mentioned: $request->validated('mentioned'),
            delayMessage: $request->validated('delay_message'),
            delayTyping: $request->validated('delay_typing'),
        );

        $message = $this->messagingService->sendText($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
            'meta' => ['request_id' => 'req_' . \Illuminate\Support\Str::random(12)],
        ], 202);
    }

    public function sendImage(SendImageRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new SendImageData(
            phone: $request->validated('phone'),
            image: $request->validated('image'),
            caption: $request->validated('caption'),
            delayMessage: $request->validated('delay_message'),
            viewOnce: (bool) $request->validated('view_once', false),
        );

        $message = $this->messagingService->sendImage($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
        ], 202);
    }

    public function sendAudio(SendAudioRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new SendAudioData(
            phone: $request->validated('phone'),
            audio: $request->validated('audio'),
            delayMessage: $request->validated('delay_message'),
            waveform: (bool) $request->validated('waveform', true),
        );

        $message = $this->messagingService->sendAudio($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
        ], 202);
    }

    public function sendReaction(SendReactionRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new SendReactionData(
            phone: $request->validated('phone'),
            reaction: $request->validated('reaction'),
            messageId: $request->validated('message_id'),
            delayMessage: $request->validated('delay_message'),
        );

        $message = $this->messagingService->sendReaction($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
        ], 202);
    }

    public function removeReaction(RemoveReactionRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new RemoveReactionData(
            phone: $request->validated('phone'),
            messageId: $request->validated('message_id'),
            delayMessage: $request->validated('delay_message'),
        );

        $message = $this->messagingService->removeReaction($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
        ], 202);
    }

    public function sendCarousel(SendCarouselRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new SendCarouselData(
            phone: $request->validated('phone'),
            message: $request->validated('message'),
            cards: $request->validated('carousel'),
            delayMessage: $request->validated('delay_message'),
        );

        $message = $this->messagingService->sendCarousel($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
        ], 202);
    }

    public function sendPixButton(SendPixButtonRequest $request): JsonResponse
    {
        $instance = WhatsAppInstance::findOrFail($request->validated('instance_id'));

        $data = new SendPixButtonData(
            phone: $request->validated('phone'),
            pixKey: $request->validated('pix_key'),
            type: $request->validated('type'),
            merchantName: $request->validated('merchant_name'),
        );

        $message = $this->messagingService->sendPixButton($instance, $data);

        return response()->json([
            'success' => true,
            'data' => new WhatsAppMessageResource($message),
        ], 202);
    }
}

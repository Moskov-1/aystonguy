<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use Gemini\Laravel\Facades\Gemini;
use Gemini\Enums\MimeType;

class ChatController extends Controller
{
    public function index()
    {
        return view('Backend.chats.index');
    }

    public function conversation(Request $request)
    {
        $userId = auth()->id();
        
        // If user_id was null in old chats, query by receiver_id too for transition
        $chats = Chat::where(function($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('receiver_id', $userId);
            })
            ->where(function($q) {
                $q->where('sender_type', 'user')
                  ->orWhere('is_ai', true);
            })
            ->oldest()
            ->get();

        return response()->json([
            'html' => view('Backend.chats.messages', compact('chats'))->render(),
        ]);
    }

    public function transcribe(Request $request)
    {
        $request->validate([
            'audio' => 'required|mimes:webm,wav,mp3,m4a,ogg|max:10240',
        ]);

        try {
            $audioData = file_get_contents($request->file('audio')->path());
            $result = Gemini::geminiFlash()->generateContent([
                "Transcribe this audio precisely. Return ONLY the transcribed text, nothing else.",
                new \Gemini\Data\Blob(
                    mimeType: MimeType::AUDIO_WAV,
                    data: base64_encode($audioData)
                )
            ]);

            return response()->json(['text' => $result->text()]);
        } catch (\Throwable $e) {
            Log::error('Transcribe Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'nullable|string',
            'image' => 'nullable|image|max:5120',
            'audio' => 'nullable|mimes:webm,wav,mp3,aac,m4a,ogg|max:10240',
        ]);

        $userId = auth()->id();
        $filePath = null;
        $fileType = 'text';

        if ($request->hasFile('image')) {
            $filePath = 'storage/' . $request->file('image')->store('uploads/chats', 'public');
            $fileType = 'image';
        } elseif ($request->hasFile('audio')) {
            $filePath = 'storage/' . $request->file('audio')->store('uploads/chats', 'public');
            $fileType = 'voice';
        }

        // 1. Save User Message
        $userChat = Chat::create([
            'user_id' => $userId,
            'sender_type' => 'user',
            'type' => $fileType,
            'message' => $request->message,
            'file_path' => $filePath,
            'is_read' => true
        ]);

        $aiResponse = null;

        // 2. AI Processing (Gemini supports vision/audio better in the same SDK call usually)
        try {
            if ($request->hasFile('image')) {
                // Image Processing with Gemini Vision
                $imageData = base64_encode(file_get_contents($request->file('image')->path()));
                $result = Gemini::geminiFlash()->generateContent([
                    $request->message ?? "What is in this image?",
                    new \Gemini\Data\Blob(
                        mimeType: MimeType::IMAGE_JPEG,
                        data: $imageData
                    )
                ]);
                $aiResponse = $result->text();
            } elseif ($request->hasFile('audio')) {
                // Audio Processing with Gemini
                $audioFile = $request->file('audio');
                $audioData = file_get_contents($audioFile->path());
                
                // Gemini PHP 2.0+ expects Blobs this way
                // Use a more explicit prompt for transcription and response
                $prompt = $request->message ?? "Please transcribe this audio and provide a helpful response.";

                try {
                    $result = Gemini::geminiFlash()->generateContent([
                        $prompt,
                        new \Gemini\Data\Blob(
                            mimeType: MimeType::AUDIO_WAV,
                            data: base64_encode($audioData)
                        )
                    ]);
                    $aiResponse = $result->text();
                } catch (\Throwable $e) {
                    Log::error('Gemini Audio Error: ' . $e->getMessage());
                    // Fallback to OpenAI Whisper or similar if needed, 
                    // but for now let's try a simpler Gemini call
                    $result = Gemini::geminiFlash()->generateContent("The user sent an audio message but I couldn't process it. Please ask them to try again or type their message.");
                    $aiResponse = $result->text();
                }
                
                // If AI returns nothing, try a fallback transcription-only approach
                if (empty(trim($aiResponse))) {
                     $result = Gemini::geminiFlash()->generateContent([
                        "Transcribe the following audio precisely:",
                        new \Gemini\Data\Blob(
                            mimeType: MimeType::AUDIO_WAV,
                            data: base64_encode($audioData)
                        )
                    ]);
                    $aiResponse = "Transcribed Audio: " . $result->text();
                }
            } else {
                // Standard Text Fallback logic
                $result = OpenAI::chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [['role' => 'user', 'content' => $request->message]],
                ]);
                $aiResponse = $result->choices[0]->message->content;
            }
        } catch (\Throwable $e) {
            try {
                // Secondary Fallback for everything
                $result = Gemini::geminiFlash()->generateContent($request->message ?? "Hello");
                $aiResponse = $result->text();
            } catch (\Throwable $e2) {
                $aiResponse = $this->getLocalAiResponse($request->message);
            }
        }

        // 3. Save AI Message
        if ($aiResponse) {
            $aiChat = Chat::create([
                'user_id' => $userId,
                'sender_type' => 'admin', 
                'type' => 'text',
                'message' => $aiResponse,
                'is_ai' => true,
                'is_read' => true
            ]);

            return response()->json(['success' => true, 'chat' => $aiChat]);
        }

        return response()->json(['success' => false, 'message' => 'Something went wrong.'], 500);
    }

    /**
     * Local Super-Fast Fallback (Mock AI)
     * This acts as a 'Safety Net' when all external APIs are busy.
     */
    private function getLocalAiResponse($input)
    {
        $input = strtolower($input);
        
        // Simple context-aware responses for common queries
        if (str_contains($input, 'hello') || str_contains($input, 'hi')) {
            return "Hello! Both my OpenAI and Gemini engines are a bit busy right now, but I'm still here to help! How can I assist you today?";
        }
        
        if (str_contains($input, 'time')) {
            return "The current server time is " . now()->format('h:i A') . ". I apologize that my deep-thinking brains are temporarily cooling down!";
        }

        if (str_contains($input, 'who are you')) {
            return "I am Ayeston AI. My premium cloud engines are currently at their limit, so I am running on my lightweight local backup mode!";
        }

        return "I'm currently receiving too many requests. While my main cloud brains (OpenAI & Gemini) are cooling down, I can tell you that I've received your message: \"" . ucfirst($input) . "\". Please try a complex query again in about 30 seconds!";
    }

    public function destroy(Chat $chat)
    {
        if ($chat->file_path) {
            $realPath = str_replace('storage/', '', $chat->file_path);
            Storage::disk('public')->delete($realPath);
        }
        $chat->delete();
        return response()->json(['success' => true]);
    }
}

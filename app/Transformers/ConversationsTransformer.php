<?php

namespace STS\Transformers;
 
use League\Fractal\TransformerAbstract;
use STS\Models\Conversation;
use STS\Models\Trip;
use STS\Transformers\TripTransformer;

class ConversationsTransformer extends TransformerAbstract
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Turn this item object into a generic array.
     *
     * @return array
     */
    public function transform(Conversation $conversation)
    {
        $data = [
            'id' => $conversation->id,
            'type' => $conversation->type,
        ];

        $module_module_coordinate_by_message = config('carpoolear.module_coordinate_by_message', false);
        if ($module_module_coordinate_by_message) {
            $trip = Trip::find($conversation->trip_id);
            // old client, maybe null
            if ($trip) {
                $tripTransformer = new TripTransformer($this->user);
                if ($trip->return_trip_id) {
                    $returnTrip = Trip::find($trip->return_trip_id);
                    if ($returnTrip) {
                        $data['return_trip'] = $tripTransformer->transform($returnTrip);
                    }
                }
                $data['trip'] = $tripTransformer->transform($trip);
            }
        }

        switch ($conversation->type) {
            case Conversation::TYPE_PRIVATE_CONVERSATION:
                $width = $conversation->users()->where('id', '<>', $this->user->id)->first();
                if ($width) {
                    $data['title'] = $width->name;
                    $data['image'] = $width->image ? '/image/profile/'.$width->image : '';
                    $data['other_user_identity_validated_at'] = $width->identity_validated_at ? $width->identity_validated_at->toDateTimeString() : null;
                } else {
                    // Handle case where no other user is found
                    $data['title'] = 'Unknown User';
                    $data['image'] = '';
                    $data['other_user_identity_validated_at'] = null;
                }
                break;
            default:
                $data['title'] = $conversation->title;
                $data['image'] = '';
                $data['other_user_identity_validated_at'] = null;
                break;
        }

        $data['unread'] = ! $conversation->read($this->user);
        $data['update_at'] = $conversation->updated_at ? $conversation->updated_at->toDateTimeString() : null;

        $m = $conversation->messages()->orderBy('created_at', 'desc')->first();
        if ($m) {
            $transformer = new MessageTransformer($this->user);
            $data['last_message'] = $transformer->transform($m);
        } else {
            $data['last_message'] = null;
        }

        $data['users'] = [];
        foreach ($conversation->users as $u) {
            $data['users'][] = [
                'id' => $u->id,
                'name' => $u->name,
                'last_connection' => $u->last_connection ? $u->last_connection->toDateTimeString() : null,
                'identity_validated_at' => $u->identity_validated_at ? $u->identity_validated_at->toDateTimeString() : null,
            ];
        }

        return $data;
    }
}

<?php

namespace App\Ai\Agents;

use App\Ai\Tools\AddCar;
use App\Ai\Tools\AddRental;
use App\Ai\Tools\AddUser;
use App\Ai\Tools\DeleteCar;
use App\Ai\Tools\DeleteRental;
use App\Ai\Tools\DeleteUser;
use App\Ai\Tools\EditCar;
use App\Ai\Tools\EditRental;
use App\Ai\Tools\EditUser;
use App\Ai\Tools\GetUsers;
use App\Ai\Tools\SearchCar;
use App\Ai\Tools\SearchRental;
use App\Ai\Tools\SearchUser;
use Laravel\Ai\Concerns\RemembersConversations as RemembersConversationsConcern;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

class Jarvis implements Agent, HasTools, RemembersConversations
{
    use Promptable, RemembersConversationsConcern;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'You are a helpful assistant.';
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            new GetUsers,
            new SearchUser,
            new AddUser,
            new EditUser,
            new DeleteUser,
            new SearchCar,
            new AddCar,
            new EditCar,
            new DeleteCar,
            new SearchRental,
            new AddRental,
            new EditRental,
            new DeleteRental,
        ];
    }
}

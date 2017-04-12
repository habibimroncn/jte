<?php

namespace TextOperation\Client;

use TextOperation\TextOperation;

class Synchronized implements ClientState
{
    /**
      * @see TextOperation\Client\ClientState
      */
     public function applyClient(Client $client, TextOperation $operation)
     {
         $client->sendOperation($client->getRevision(), $operation);

         return new AwaitingOperation($operation);
     }

      /**
       * @see TextOperation\Client\ClientState
       */
      public function applyServer(Client $client, TextOperation $operation)
      {
          $client->applyOperation($operation);

          return $this;
      }

      /**
       * @see TextOperation\Client\ClientState
       */
      public function serverAck(Client $client)
      {
          return $this;
      }
}

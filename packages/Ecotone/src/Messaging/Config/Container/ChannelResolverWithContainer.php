<?php

namespace Ecotone\Messaging\Config\Container;

use Ecotone\Messaging\Handler\ChannelResolver;
use Ecotone\Messaging\MessageChannel;
use Psr\Container\ContainerInterface;

class ChannelResolverWithContainer implements ChannelResolver
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function resolve($channelName): MessageChannel
    {
        if ($channelName instanceof MessageChannel) {
            return $channelName;
        }
        return $this->container->get(new ChannelReference($channelName));
    }

    public function hasChannelWithName(string $channelName): bool
    {
        $id = (string) new ChannelReference($channelName);
        return $this->container->has($id);
    }
}

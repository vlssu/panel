<?php

/**
 * Pterodactyl - Panel
 * Copyright (c) 2015 - 2017 Dane Everitt <dane@daneeveritt.com>.
 *
 * This software is licensed under the terms of the MIT license.
 * https://opensource.org/licenses/MIT
 */

namespace Pterodactyl\Console\Commands\Node;

use Illuminate\Console\Command;
use Pterodactyl\Services\Nodes\NodeCreationService;

class MakeNodeCommand extends Command
{
    /**
     * @var \Pterodactyl\Services\Nodes\NodeCreationService
     */
    protected $creationService;

    /**
     * @var string
     */
    protected $signature = 'p:node:make
                            {--name= : 用于标识节点的名称。}
                            {--description= : 用于标识节点的描述。}
                            {--locationId= : 一个有效的地域 ID。}
                            {--fqdn= : 请输入用于连接守护程序的域名 (例如 node.example.com)。仅在 您没有为此节点使用 SSL 连接的情况下才可以使用 IP 地址。}
                            {--public= : 节点应该是公共的还是私有的？ （公共 = 1 / 私人 = 0）。}
                            {--scheme= : 应该使用哪种方案？ （启用 SSL=https / 禁用 SSL=http）。}
                            {--proxy= : 守护进程是否使用了代理？ （是=1 / 否=0）。}
                            {--maintenance= : 是否应该启用维护模式？ （启用维护模式 = 1 / 禁用维护模式 = 0）。}
                            {--maxMemory= : 设置最大内存容量。}
                            {--overallocateMemory= : 输入要过度分配的内存容量 (% or -1 to overallocate the maximum).}
                            {--maxDisk= : 设置最大存储容量。}
                            {--overallocateDisk= : 输入要过度分配的存储容量 (% or -1 to overallocate the maximum).}
                            {--uploadSize= : 输入最大文件上传大小}
                            {--daemonListeningPort= : 输入wings监听端口。}
                            {--daemonSFTPPort= : 输入wings SFTP监听端口。}
                            {--daemonBase= : 输入服务器文件存储目录}';

    /**
     * @var string
     */
    protected $description = '通过CLI在系统上创建一个新节点。';

    /**
     * Handle the command execution process.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function handle(NodeCreationService $creationService)
    {
        $this->creationService = $creationService;

        $data['name'] = $this->option('name') ?? $this->ask('输入用于区分此节点与其他节点的简短标识符');
        $data['description'] = $this->option('description') ?? $this->ask('输入描述以识别节点');
        $data['location_id'] = $this->option('locationId') ?? $this->ask('输入有效的地域 ID');
        $data['fqdn'] = $this->option('fqdn') ?? $this->ask('请输入用于连接守护程序的域名 (例如 node.example.com)。仅在 您没有为此节点使用 SSL 连接的情况下才可以使用 IP 地址');

        // Note, this function will also resolve CNAMEs for us automatically,
        // there is no need to manually resolve them here.
        //
        // Using @ as workaround to fix https://bugs.php.net/bug.php?id=73149
        $records = @dns_get_record($data['fqdn'], DNS_A + DNS_AAAA);
        if (empty($records)) {
            $this->error('提供的 FQDN(域名) 或 IP 地址无法解析为有效的 IP 地址。');

            return;
        }
        $data['public'] = $this->option('public') ?? $this->confirm('这个节点应该是公开的吗？ 请注意，将节点设置为私有您将无法使用自动部署到该节点的能力。', true);
        $data['scheme'] = $this->option('scheme') ?? $this->anticipate(
            '请为 SSL 输入 https 或为非 SSL 连接输入 http',
            ['https', 'http'],
            'https'
        );
        if (filter_var($data['fqdn'], FILTER_VALIDATE_IP) && $data['scheme'] === 'https') {
            $this->error('需要解析为公共 IP 地址的完全限定域名才能为此节点使用 SSL。');

            return;
        }
        $data['behind_proxy'] = $this->option('proxy') ?? $this->confirm('您的 FQDN(域名) 是否使用了代理？');
        $data['maintenance_mode'] = $this->option('maintenance') ?? $this->confirm('是否应该启用维护模式？');
        $data['memory'] = $this->option('maxMemory') ?? $this->ask('输入最大内存容量');
        $data['memory_overallocate'] = $this->option('overallocateMemory') ?? $this->ask('输入要过度分配的内存容量，-1 将禁用检查，0 将阻止创建新服务器');
        $data['disk'] = $this->option('maxDisk') ?? $this->ask('输入最大存储空间容量');
        $data['disk_overallocate'] = $this->option('overallocateDisk') ?? $this->ask('输入要过度分配的存储空间容量，-1 将禁用检查，0 将阻止创建新服务器');
        $data['upload_size'] = $this->option('uploadSize') ?? $this->ask('输入最大文件上传大小', '100');
        $data['daemonListen'] = $this->option('daemonListeningPort') ?? $this->ask('输入wings监听端口', '8080');
        $data['daemonSFTP'] = $this->option('daemonSFTPPort') ?? $this->ask('输入wings SFTP监听端口', '2022');
        $data['daemonBase'] = $this->option('daemonBase') ?? $this->ask('输入服务器文件存储目录', '/var/lib/pterodactyl/volumes');

        $node = $this->creationService->handle($data);
        $this->line('在地域 ' . $data['location_id'] . ' 上成功创建了一个名为 ' . $data['name'] . ' 且 id 为 ' . $node->id . ' 的新节点。');
    }
}

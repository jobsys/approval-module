# **Approval** 审核模块

该模块提供了基础的审核流程定义，审核流程实例，审核流程任务，审核流程任务实例，审核流程任务实例记录等功能。

## 模块安装
```bash

# 安装依赖
composer require jobsys/approval-module --dev

# 启用模块
php artisan module:enable Approval && php artisan module:publish-migration Approval && php artisan migrate
```

### 配置

#### 模块配置

```php
"Approval" => [
    "route_prefix" => "manager",                                                    // 路由前缀
    "approval_types" => [                                                           // 审核类型列表
        'ProjectCreated' => [                                                       // 审核类型名称
            'displayName' => '项目创建',                                             // 审核类型显示名称           
            'type' => 'project-created',                                            // 审核类型标识    
            'approve_todo' => \App\Notifications\ProjectApproveTodo::class,         // 审核待办通知类
            'approved_event' => \App\Events\ProjectApproved::class,                 // 审核结果事件通知类
        ],
    ]
]
```

## 模块功能

### 审核功能

在模块配置中先添加项目中的审核类型，然后在管理后台进行审核的流程定义。可以为流程设置多个审核流程及节点的可见性，具体见设置页面。后续可以在业务中创建
审核任务，以及进行任务的审核。

#### 开发规范

1. 在  `config/module.php` 的 `Approval` => `approval_types` 添加审核类型。

2. 给被审核的 `Model` 添加 `Modules\Approval\Traits\Approvable` trait。

3. 在业务逻辑中创建审核任务，如下：

   ```php
   $project = Project::find(1);
   $approvalService->createApprovalTask($project, config('module.Approval.approval_types.ProjectCreated'));
   ```

   > 更推荐使用 `Event/Listener` 的方式创建审核任务，对于原业务流程侵入性更低，且可以在 `Listener` 中处理多种事件，其流程如下：

    1. 在 `app/Events` 目录下创建 `ProjectCreated.php` 事件类

    2. 在 `app/Listeners` 目录下创建 `ProjectCreatedListener.php` 监听类并创建审核任务

    3. 在 `app/Providers/EventServiceProvider.php` 中注册事件和监听类

    4. 在业务逻辑中触发事件，如下：

       ```php
       $project = Project::find(1);
       ProjectCreated::dispatch($project);
       ```

4. 使用 `Modules\Approval\Services\ApprovalService` 中的 `getUserApprovable` 获取用户的审核任务，如下：

   ```php
   $query = Project::with(['department:id,name'])->when($name, function ($query) use ($name) {
       return $query->where('name', 'like', '%' . $name . '%');
   })
   
   $pagination = $service->getUserApprovable($query, $this->login_user, $process, $approval_status)->paginate();
   ```

   > $query 参数是一个 Approvable Model 的查询对象，可以使用 with, where 等方法进行查询，进行业务处理后返回。

5. 在前端引入 `Modules\Approval\Resources\views\web\components\ApprovalBox.vue`，其中已经封装了 `审核功能`，该组件接收三个参数:

   ```js
   defineProps({
       tasks: { type: Array, default: () => [] },                  // 审核流程的中该审核对象的任务列表
       histories: { type: Array, default: () => [] },              // 审核对象的审核历史记录                 
       currentTask: { type: Object, default: () => null },         // 当前审核任务
   })
   ```

   > 以上三个参数在上一步的 `getUserApprovable` 都已经封装到了审核对象中，可以直接使用。

## 模块代码

### 数据表

```bash
2014_10_12_000002_create_approval_tables.php              # 审核模块数据表
```

### 数据模型/Scope

```bash
Modules\Approval\Entities\ApprovalProcess                # 审核流程
Modules\Approval\Entities\ApprovalProcessNode            # 审核流程节点
Modules\Approval\Entities\ApprovalTask                   # 审核任务
Modules\Approval\Entities\ApprovalTaskHistory            # 审核任务历史记录                       
```

### 枚举

```php
// 审核状态
enum ApprovalStatus: string
{
    case Pending = 'pending'; //待审批
    case Approved = 'approved'; //审批通过
    case Rejected = 'rejected'; //审批驳回
    case Skipped = 'skipped'; //审批跳过
    case Updated = 'updated'; //审批对象已更新
}

//后续节点对于审核对象的可见性
enum ApprovalSubsequentAction: string
{
    case Invisible = 'invisible'; //不可见
    case Visible = 'visible'; //可见
    case Approve = 'approve'; //可审批
}
```

### 辅助函数

#### 基础

+ `approval_status_options`

  ```php
  /**
   * 获取前端可用的审批状态选项
   * @return array[]
   */
  function approval_status_options(): array
  ```

### Controller

```bash
Modules\Approval\Http\Controllers\ApprovalController        # 审核流程的增删改查以及审核操作的 API
```

### UI

#### PC 端页面

```bash
web/PageApprovalProcess.vue           # 审核流程定义管理页面
```

#### PC 组件

```bash
web/components/ApprovalBox.vue        # 审核组件，整合了审核操作，审核历史，审核流程等功能
```

### Service

+ **`ApprovalService`**

    - `createApprovalTask` 创建审批任务

      ```php
      /**
      * 创建审批任务
      * @param Model $approvable
      * @param array $config
      * @return array
      */
      public function createApprovalTask(Model $approvable, array $config): array
      ```

    - `getUserApprovable` 获取用户的审批任务

      ```php
      /**
      * 获取用户待审批的审批对象
      * @param Builder $builder
      * @param User $user
      * @param ApprovalProcess $process
      * @param string $status
      * @return Builder
      */
      public function getUserApprovable(Builder $builder, User $user, ApprovalProcess $process, string $status = ''): Builder
      ```

    - `approve` 审批

      ```php
      /**
      * 审批
      * @param User $user
      * @param Model $approvable
      * @param ApprovalProcess $process
      * @param string $approval_status
      * @param string $approval_comment
      * @param bool $is_snapshot
      * @return array
      */
      public function approve(User $user, Model $approvable, ApprovalProcess $process, string $approval_status, string $approval_comment = '', bool $is_snapshot = false): array
      ```

    - `getUserApprovableTask` 获取当前用户对于某个审批对象的审批任务

      ```php
      /**
      * 获取当前用户对于某个审批对象的审批任务
      * @param User $user
      * @param ApprovalProcess $process
      * @param Model $approvable
      * @return ApprovalTask|null
      */
      public function getUserApprovableTask(User $user, ApprovalProcess $process, Model $approvable): ApprovalTask|null
      ```

    - `getApprovalDetail` 为审批对象添加审批历史和详情

      ```php
      /**
      * 为审批对象添加审批历史和详情
      * @param User $user
      * @param ApprovalProcess $process
      * @param Model $approvable
      * @return void
      */
      public function getApprovalDetail(User $user, ApprovalProcess $process, Model $approvable): void
      ```

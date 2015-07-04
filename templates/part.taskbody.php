<div class="task-body" oc-drop-task type="task" taskID="{{ task.id }}">
    <div class="percentdone" style="width:{{ task.complete }}%; background-color:{{ getTaskColor(task.calendarid) }};"></div>
    <a class="task-checkbox handler" name="toggleCompleted" ng-click="toggleCompleted(task.id)">
        <span class="icon task-checkbox" ng-class="{'task-checked': task.completed}"></span>
    </a>
    <a class="icon task-separator"></a>
    <a class="task-star handler" ng-click="toggleStarred(task.id)">
        <span class="icon task-star faded" ng-class="{'high':task.priority>5,'medium':task.priority==5,'low':task.priority > 0 && task.priority < 5}"></span>
    </a>
    <a class="task-addsubtask handler add-subtask" ng-click="showSubtaskInput(task.uid)" oc-click-focus="{selector: '.add-subtask input', timeout: 0}">
        <span class="icon addsubtask" title="<?php p($l->t('add a subtask to')); ?> {{ task.name }}"></span>
    </a>
    <a class="duedate" ng-class="{overdue: TasksModel.overdue(task.due)}">{{ task.due | dateTaskList }}</a>
    <a ng-show="route.listID=='week'" class="listname" >{{ getTaskList(task.calendarid) }}</a>
    <div class="title-wrapper"  ng-class="{attachment: task.note!='', subtasks: hasSubtasks(task), subtaskshidden: task.hidesubtasks}">
        <span class="title">{{ task.name }}</span>
        <span class="icon task-attachment"></span>
        <span class="icon subtasks handler" ng-click="toggleSubtasks(task.id)"></span>
        <span class="categories-list">
            <ul>
                <li ng-repeat="category in task.categories"><span>{{ category }}</span></li>
            </ul>
        </span>
    </div>
</div>
<div class="subtasks-container" ng-hide="task.hidesubtasks">
    <ol>
        <li class="task-item ui-draggable handler add-subtask" ng-show="status.addSubtaskTo == task.uid">
            <form ng-submit="addTask(status.taskName,task.uid)" name="addTaskForm">
                <input ng-disabled="isAddingTask" ng-click="focusInput()" class="transparent" placeholder="{{ getSubAddString(task.name) }}" ng-model="status.taskName"
                        ng-keydown="checkTaskInput($event)"/>
            </form>
        </li>
        <li ng-repeat="task in getSubTasks(tasks,task) | filter:filterTasksByString(task) | orderBy:'priority':true" ng-click="openDetails(task.id,$event)" class="task-item ui-draggable handler"
         ng-class="{done: task.completed}" ng-include="'part.taskbody'" taskID="{{ task.id }}" oc-drag-task></li>
    </ol>
</div>
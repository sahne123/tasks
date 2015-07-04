###

ownCloud - Tasks

@author Raimund Schlüßler
@copyright 2013

This library is free software; you can redistribute it and/or
modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
License as published by the Free Software Foundation; either
version 3 of the License, or any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU AFFERO GENERAL PUBLIC LICENSE for more details.

You should have received a copy of the GNU Affero General Public
License along with this library.  If not, see <http://www.gnu.org/licenses/>.

###
angular.module('Tasks').controller 'TasksController',
['$scope', '$window', '$routeParams', 'TasksModel', 'ListsModel',
'CollectionsModel', 'TasksBusinessLayer', '$location',
'SettingsBusinessLayer', 'SearchBusinessLayer'
($scope, $window, $routeParams, TasksModel, ListsModel,
CollectionsModel, TasksBusinessLayer, $location,
SettingsBusinessLayer, SearchBusinessLayer) ->

	class TasksController

		constructor: (@_$scope,@_$window,@_$routeParams,
					@_$tasksmodel,@_$listsmodel,@_$collectionsmodel,
					@_tasksbusinesslayer, @$location, @_settingsbusinesslayer,
					@_searchbusinesslayer) ->

			@_$scope.tasks = @_$tasksmodel.getAll()
			@_$scope.lists = @_$listsmodel.getAll()

			@_$scope.days = [0,1,2,3,4,5,6]

			@_$scope.isAddingTask = false

			@_$scope.focusInputField = false

			@_$scope.TasksModel = @_$tasksmodel

			@_$scope.TasksBusinessLayer = @_tasksbusinesslayer

			@_$scope.getAddString = () ->
				if angular.isDefined(list =
				_$listsmodel.getById(_$listsmodel.getStandardList()))
					switch _$scope.route.listID
						when 'starred'
							return t('tasks','Add an important item in "%s"...')
							.replace('%s',list.displayname)
						when 'today'
							return t('tasks','Add an item due today in "%s"...')
							.replace('%s',list.displayname)
						when 'all'
							return t('tasks','Add an item in "%s"...')
							.replace('%s',list.displayname)
						when 'current'
							return t('tasks','Add a current item in "%s"...')
							.replace('%s',list.displayname)
						when 'completed', 'week'
							return null
						else
							if angular.isDefined(_$listsmodel.getById(_$scope.route.listID))
								return t('tasks','Add an item in "%s"...')
								.replace('%s',
								_$listsmodel.getById(_$scope.route.listID).displayname)

			@_$scope.getSubAddString = (taskname) ->
				return t('tasks','Add a subtask to "%s"...')
								.replace('%s', taskname)

			@_$scope.showSubtaskInput = (uid) ->
				_$scope.status.addSubtaskTo = uid

			@_$scope.showInput = () ->
				if _$scope.route.listID in ['completed', 'week']
					return false
				else
					return true

			@_$scope.focusTaskInput = () ->
				_$scope.status.focusTaskInput = true

			@_$scope.focusSubtaskInput = () ->
				_$scope.status.focusSubtaskInput = true

			@_$scope.openDetails = (id,$event) ->
				if $($event.currentTarget).is($($event.target).closest('.handler'))
					listID = _$scope.route.listID
					$location.path('/lists/'+listID+'/tasks/'+id)

			@_$scope.toggleCompleted = (taskID) ->
				if _$tasksmodel.completed(taskID)
					_tasksbusinesslayer.uncompleteTask(taskID)
				else
					_tasksbusinesslayer.completeTask(taskID)

			@_$scope.toggleStarred = (taskID) ->
				if _$tasksmodel.starred(taskID)
					_tasksbusinesslayer.unstarTask(taskID)
				else
					_$tasksmodel.star(taskID)
					_tasksbusinesslayer.starTask(taskID)

			@_$scope.toggleHidden = () ->
				_settingsbusinesslayer.toggle('various','showHidden')

			@_$scope.filterTasks = (task, filter) ->
				return (task) ->
					return _$tasksmodel.filterTasks(task, filter)

			@_$scope.getSubTasks = (tasks,parent) ->
				ret = []
				for task in tasks
					if task.related == parent.uid
						ret.push(task)
				return ret

			@_$scope.hasNoParent = (task) ->
				return (task) ->
					return _$tasksmodel.hasNoParent(task)

			@_$scope.hasSubtasks = (task) ->
				return _$tasksmodel.hasSubtasks(task.uid)

			@_$scope.toggleSubtasks = (taskID) ->
				if _$tasksmodel.hideSubtasks(taskID)
					_tasksbusinesslayer.unhideSubtasks(taskID)
				else
					_tasksbusinesslayer.hideSubtasks(taskID)

			@_$scope.filterTasksByString = (task) =>
				return (task) ->
					filter = _searchbusinesslayer.getFilter()
					return _$tasksmodel.filterTasksByString(task, filter)

			@_$scope.dayHasEntry = () ->
				return (date) ->
					tasks = _$tasksmodel.getAll()
					for task in tasks
						if task.completed
							continue
						if _$tasksmodel.taskAtDay(task, date)
							return true
					return false

			@_$scope.getTasksAtDay = (tasks, day) ->
				ret = []
				for task in tasks
					if _$tasksmodel.taskAtDay(task, day)
						ret.push(task)
				return ret

			@_$scope.filterLists = () ->
				return (list) ->
					return _$scope.getCount(list.id,_$scope.route.listID)

			@_$scope.getCount = (listID,type) ->
				filter = _searchbusinesslayer.getFilter()
				return _$listsmodel.getCount(listID,type,filter)

			@_$scope.getCountString = (listID,type) ->
				filter = _searchbusinesslayer.getFilter()
				return n('tasks', '%n Completed Task', '%n Completed Tasks',
						_$listsmodel.getCount(listID,type,filter))

			@_$scope.addTask = (taskName,related='') ->

				_$scope.isAddingTask = true

				task = {
					tmpID:		'newTask' + Date.now()
					calendarid:	null
					related:	related
					name:		taskName
					starred:	false
					due:		false
					start:		false
					completed:	false
					complete:	'0'
					note:		false
				}

				if (_$scope.route.listID in
				['starred', 'today', 'week', 'all', 'completed', 'current'])
					task.calendarid = _$listsmodel.getStandardList()
					if _$scope.route.listID == 'starred'
						task.starred = true
					if _$scope.route.listID == 'today'
						task.due = moment().startOf('day').format("YYYYMMDDTHHmmss")
					if _$scope.route.listID == 'current'
						task.start = moment().format("YYYYMMDDTHHmmss")
				else
					task.calendarid = _$scope.route.listID

				_tasksbusinesslayer.addTask task
				, (data) =>
					_$tasksmodel.add(data)
					_$scope.isAddingTask = false
				, =>
					_$scope.isAddingTask = false

				_$scope.status.focusTaskInput = false
				_$scope.status.focusSubtaskInput = false
				_$scope.status.addSubtaskTo = ''
				_$scope.status.taskName = ''

			@_$scope.checkTaskInput = ($event) ->
				if($event.keyCode == 27)
					$($event.currentTarget).blur()
					_$scope.status.taskName = ''
					_$scope.status.addSubtaskTo = ''
					_$scope.status.focusTaskInput = false
					_$scope.status.focusSubtaskInput = false

			@_$scope.getCompletedTasks = (listID) ->
				_tasksbusinesslayer.getCompletedTasks(listID)

			@_$scope.loadedAll = (listID) ->
				return _$listsmodel.loadedAll(listID)

			@_$scope.sortDue = (task) ->
				if task.due == null
					return 'last'
				else
					return task.due

			@_$scope.getTaskColor = (listID) ->
				return _$listsmodel.getColor(listID)

			@_$scope.getTaskList = (listID) ->
				return _$listsmodel.getName(listID)

	return new TasksController($scope, $window, $routeParams,
		TasksModel, ListsModel, CollectionsModel, TasksBusinessLayer, $location,
		SettingsBusinessLayer, SearchBusinessLayer)
]
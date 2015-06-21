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
angular.module('Tasks').directive 'ocDropTask', ($timeout) ->
	link: (scope, elm, attr) ->
		elm.droppable({
			over: (event,ui) ->
				# <- can be written prettier with AngularJS 1.4
				hovering = (tmp) ->
					$(tmp).addClass('changeParent')
				scope.timer = $timeout( hovering.bind(null,this,ui)
				,1000)
				# ->
			out: (event,ui) ->
				$timeout.cancel(scope.timer)
				$(this).removeClass('changeParent')
			deactivate: (event,ui) ->
				$timeout.cancel(scope.timer)
				$(this).removeClass('changeParent')
			drop: (event,ui) ->
				$timeout.cancel(scope.timer)
				scope.$apply(
					if attr.type == 'task' &&
					$(this).hasClass('changeParent')
						scope.TasksBusinessLayer
						.changeParent(ui.helper.attr('taskID'),$(this).attr('taskID'))
					if attr.type == 'list' &&
					!$(this).hasClass('changeParent')
						scope.TasksBusinessLayer
						.changeList(ui.helper.attr('taskID'),$(this).attr('listID'))
				)
				$(this).removeClass('changeParent')
		})
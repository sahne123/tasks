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
angular.module('Tasks').directive 'ocDragTask', ->
	link: (scope, elm, attr) ->
		elm.draggable({
			helper: "clone"
			appendTo: $('#content')
			cursorAt: { left: 150, top:15 }
			distance: 4
			start: (event,ui) ->
				$(this).addClass('visibility-hidden')
			stop: (event,ui) ->
				$(this).removeClass('visibility-hidden')
		})
<?php
/**
 * Discuss
 *
 * Copyright 2010-11 by Shaun McCormick <shaun@modx.com>
 *
 * This file is part of Discuss, a native forum for MODx Revolution.
 *
 * Discuss is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * Discuss is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Discuss; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package discuss
 */
/**
 * Handle the home page
 *
 * @package discuss
 * @subpackage controllers
 */
class DiscussHomeController extends DiscussController {

    public function getSessionPlace() {
        return 'home';
    }

    public function getPageTitle() {
        return $this->modx->getOption('discuss.forum_title');
    }

    public function process() {
        $this->handleActions();

        if (!empty($this->options['showBoards'])) {
            $this->getBoards();
        }

        if (!empty($this->options['showStatistics'])) {
            $this->getStatistics();
        }

        $this->renderActionButtons();

        if (!empty($this->options['showRecentPosts'])) {
            $this->getRecentPosts();
        }

        /* invoke render event for plugin injection of custom stuff */
        $this->setPlaceholders(array(
            'top' => '',
            'bottom' => '',
            'aboveBoards' => '',
            'belowBoards' => '',
            'aboveRecent' => '',
            'belowRecent' => '',
        ));
        $eventOutput = $this->discuss->invokeRenderEvent('OnDiscussRenderHome',$this->getPlaceholders());
        if (!empty($eventOutput)) {
            $this->setPlaceholders($eventOutput);
        }
    }

    /**
     * Handle any POST actions on the page
     * @return void
     */
    public function handleActions() {
        /* process logout */
        if (isset($this->scriptProperties['logout']) && $this->scriptProperties['logout']) {
            $response = $this->modx->runProcessor('security/logout');
            $url = $this->discuss->request->makeUrl();
            $this->modx->sendRedirect($url);
        }
        if (isset($this->scriptProperties['read']) && !empty($this->scriptProperties['read'])) {
            $c = array(
                'board' => 0,
            );
            if (!empty($this->scriptProperties['category'])) $c['category'] = (int)$this->scriptProperties['category'];
            $this->discuss->hooks->load('thread/read_all',$c);
        }
    }

    /**
     * Get boards
     * @return void
     */
    public function getBoards() {
        $c = array(
            'board' => 0,
        );
        if (!empty($this->scriptProperties['category'])) $c['category'] = (int)$this->scriptProperties['category'];
        $boards = $this->discuss->hooks->load('board/getlist',$c);
        if (!empty($boards)) {
            $this->setPlaceholder('boards',$boards);
        }
    }

    /**
     * @return void
     */
    public function renderActionButtons() {
        /* action buttons */
        $actionButtons = array();
        if ($this->discuss->user->isLoggedIn) { /* if logged in */
            $actionButtons[] = array('url' => $this->discuss->request->makeUrl('',array('read' => 1)), 'text' => $this->modx->lexicon('discuss.mark_all_as_read'));

            $authLink = $this->discuss->request->makeUrl('logout');
            $authMsg = $this->modx->lexicon('discuss.logout');
            $this->modx->setPlaceholder('discuss.authLink','<a href="'.$authLink.'">'.$authMsg.'</a>');
            $actionButtons[] = array('url' => $authLink, 'text' => $authMsg);
        } else { /* if logged out */
            $authLink = $this->discuss->request->makeUrl('login');
            $authMsg = $this->modx->lexicon('discuss.login');
            $this->modx->setPlaceholder('discuss.authLink','<a href="'.$authLink.'">'.$authMsg.'</a>');

            if (!empty($options['showLoginForm'])) {
                $this->modx->setPlaceholder('discuss.loginForm',$this->discuss->getChunk('disLogin'));
            }
        }
        $placeholders['actionbuttons'] = $this->discuss->buildActionButtons($actionButtons,'dis-action-btns right');
        unset($authLink,$authMsg,$actionButtons);
    }

    /**
     * Get the statistics for the bottom area of the home page
     * @return void
     */
    public function getStatistics() {
        $this->setPlaceholder('totalPosts',number_format((int)$this->modx->getCount('disPost')));
        $this->setPlaceholder('totalTopics',number_format((int)$this->modx->getCount('disPost',array('parent' => 0))));
        $this->setPlaceholder('totalMembers',number_format((int)$this->modx->getCount('disUser')));

        /* active in last 40 */
        if ($this->modx->getOption('discuss.show_whos_online',null,true) && $this->modx->hasPermission('discuss.view_online')) {
            $this->setPlaceholder('activeUsers',$this->discuss->hooks->load('user/active_in_last'));
        } else {
            $this->setPlaceholder('activeUsers','');
        }

        /* total active */
        $this->setPlaceholder('totalMembersActive',number_format((int)$this->modx->getCount('disSession',array('user:!=' => 0))));
        $this->setPlaceholder('totalVisitorsActive',number_format((int)$this->modx->getCount('disSession',array('user' => 0))));

        /* forum activity */
        $activity = $this->modx->getObject('disForumActivity',array(
            'day' => date('Y-m-d'),
        ));
        if (!$activity) {
            $activity = $this->modx->newObject('disForumActivity');
            $activity->set('day',date('Y-m-d'));
            $activity->save();
        }
        $this->setPlaceholders($activity->toArray('activity.'));
    }


    public function getRecentPosts() {
        $cacheKey = 'discuss/board/recent/'.$this->discuss->user->get('id');
        $recent = $this->modx->cacheManager->get($cacheKey);
        if (empty($recent)) {
            $recent = $this->discuss->hooks->load('post/recent');
            $this->modx->cacheManager->set($cacheKey,$recent,$this->modx->getOption('discuss.cache_time',null,3600));
        }
        $this->setPlaceholder('recent_posts',$recent['results']);
        unset($recent);
    }

    public function getBreadcrumbs() {
        $trail = array();
        if (!empty($scriptProperties['category'])) {
            $category = $this->modx->getObject('disCategory',$scriptProperties['category']);
        }
        if (!empty($category)) {
            $trail[] = array(
                'text' => $this->modx->getOption('discuss.forum_title'),
                'url' => $this->discuss->request->makeUrl(),
            );
            $trail[] = array(
                'text' => $category->get('name'),
                'active' => true
            );
        } else {
            $trail[] = array('text' => $this->modx->getOption('discuss.forum_title'),'active' => true);
        }
        return $trail;
    }
}
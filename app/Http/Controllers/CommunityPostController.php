<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Community;
use App\Models\Post;
use App\Models\PostVote;
use App\Notifications\PostReportNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Intervention\Image\Facades\Image;

class CommunityPostController extends Controller
{

    public function index(Community $community)
    {
        $posts = $community->posts()->latest('id')->paginate(10);
    }


    public function create(Community $community)
    {
        return view('posts.create', compact('community'));
    }


    public function store(StorePostRequest $request, Community $community)
    {
        $post = $community->posts()->create([
            'user_id' => auth()->id(),
            'title' => $request->title,
            'post_text' => $request->post_text ?? null,
            'post_url' => $request->post_url ?? null
        ]);

        if ($request->hasFile('post_image')) {
            $image = $request->file('post_image')->getClientOriginalName();
            $request->file('post_image')
                ->storeAs('posts/' . $post->id, $image);
            $post->update(['post_image' => $image]);

            $file = Image::make(storage_path('app/public/posts/' . $post->id . '/' . $image));
            $file->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $file->save(storage_path('app/public/posts/' . $post->id . '/thumbnail_' . $image));
        }

        return redirect()->route('communities.show', $community);
    }


    public function show(Community $community, Post $post)
    {
        return view('posts.show', compact('community', 'post'));
    }


    public function edit(Community $community, Post $post)
    {
        if (Gate::denies('edit-post', $post)) {
            abort(403);
        }

        return view('posts.edit', compact('community', 'post'));
    }


    public function update(StorePostRequest $request, Community $community, Post $post)
    {
        if ($post->user_id != auth()->id()) {
            abort(403);
        }

        $post->update($request->validated());

        if ($request->hasFile('post_image')) {
            $image = $request->file('post_image')->getClientOriginalName();
            $request->file('post_image')
                ->storeAs('posts/' . $post->id, $image);

            if ($post->post_image != '' && $post->post_image != $image) {
                unlink(storage_path('app/public/posts/' . $post->id . '/' . $post->post_image));
            }

            $post->update(['post_image' => $image]);

            $file = Image::make(storage_path('app/public/posts/' . $post->id . '/' . $image));
            $file->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $file->save(storage_path('app/public/posts/' . $post->id . '/thumbnail_' . $image));
        }

        return redirect()->route('communities.posts.show', [$community, $post]);
    }


    public function destroy(Community $community, Post $post)
    {
        if (Gate::denies('delete-post', $post)) {
            abort(403);
        }

        $post->delete();

        return redirect()->route('communities.show', [$community]);
    }

    public function vote($post_id, $vote)
    {
        $post = Post::with('community')->findOrFail($post_id);

        if (!PostVote::where('post_id', $post_id)->where('user_id', auth()->id())->count()
            && in_array($vote, [-1, 1]) && $post->user_id != auth()->id()) {
            PostVote::create([
                'post_id' => $post_id,
                'user_id' => auth()->id(),
                'vote' => $vote
            ]);
        }
        return redirect()->route('communities.show', $post->community);

    }

    public function report($post_id)
    {
        $post = Post::with('community.user')->findOrFail($post_id);

//        $post->community->user->notify(new PostReportNotification($post));
        return redirect()->route('communities.posts.show', [$post->community, $post])
            ->with('message', 'Your report has been sent.');
    }
}

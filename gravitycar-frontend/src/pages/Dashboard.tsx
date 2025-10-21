import { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import { apiService } from '../services/api';
import type { User, Movie, MovieQuote } from '../types';

interface TriviaGameScore {
  id: string;
  name: string;
  score: number;
  game_completed_at: string | null;
  created_by_name?: string;
  created_at: string;
}

const Dashboard = () => {
  const { user } = useAuth();
  const [stats, setStats] = useState({
    totalUsers: 0,
    totalMovies: 0,
    totalQuotes: 0,
    isLoading: true
  });

  const [recentData, setRecentData] = useState<{
    triviaGames: TriviaGameScore[];
    movies: Movie[];
    quotes: MovieQuote[];
  }>({
    triviaGames: [],
    movies: [],
    quotes: []
  });

  useEffect(() => {
    const fetchDashboardData = async () => {
      try {
        // Fetch basic stats (just first page for count estimates)
        const [usersResponse, moviesResponse, quotesResponse, triviaGamesResponse] = await Promise.all([
          apiService.getUsers(1, 5),
          apiService.getMovies(1, 5),
          apiService.getMovieQuotes(1, 5),
          apiService.getList('Movie_Quote_Trivia_Games', 1, 20, { 
            sort: 'game_completed_at:desc' // Sort by most recent completion first
          })
        ]);

        setStats({
          totalUsers: usersResponse.pagination?.total_items || 0,
          totalMovies: moviesResponse.pagination?.total_items || 0,
          totalQuotes: quotesResponse.pagination?.total_items || 0,
          isLoading: false
        });

        // Filter trivia games: only completed games with score >= 100
        // Sort by completion date (most recent first)
        const filteredGames = ((triviaGamesResponse.data || []) as TriviaGameScore[])
          .filter((game) => 
            game.game_completed_at !== null && 
            game.score >= 100
          )
          .sort((a, b) => {
            const dateA = new Date(a.game_completed_at || a.created_at).getTime();
            const dateB = new Date(b.game_completed_at || b.created_at).getTime();
            return dateB - dateA; // Descending order (most recent first)
          })
          .slice(0, 5); // Show top 5

        setRecentData({
          triviaGames: filteredGames,
          movies: moviesResponse.data || [],
          quotes: quotesResponse.data || []
        });
      } catch (error) {
        console.error('Failed to fetch dashboard data:', error);
        setStats(prev => ({ ...prev, isLoading: false }));
      }
    };

    fetchDashboardData();
  }, []);

  if (stats.isLoading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="text-lg text-gray-600">Loading dashboard...</div>
      </div>
    );
  }

  // Helper function to get display name for user
  const getUserDisplayName = () => {
    if (!user) return '';
    
    // Prefer first_name + last_name
    if (user.first_name && user.last_name) {
      return `${user.first_name} ${user.last_name}`;
    }
    
    // Fallback to first_name only
    if (user.first_name) {
      return user.first_name;
    }
    
    // Fallback to username or email
    return user.username || user.email;
  };

  return (
    <div className="space-y-6">
      {/* Welcome Section */}
      <div className="bg-white shadow rounded-lg p-6">
        <h1 className="text-2xl font-bold text-gray-900 mb-2">
          Welcome back, {getUserDisplayName()}!
        </h1>
        <p className="text-gray-600">
          Here's an overview of your Gravitycar application data.
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <div className="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                <span className="text-white font-semibold">U</span>
              </div>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Total Users</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.totalUsers}</p>
            </div>
          </div>
        </div>

        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <div className="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                <span className="text-white font-semibold">M</span>
              </div>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Total Movies</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.totalMovies}</p>
            </div>
          </div>
        </div>

        <div className="bg-white shadow rounded-lg p-6">
          <div className="flex items-center">
            <div className="flex-shrink-0">
              <div className="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                <span className="text-white font-semibold">Q</span>
              </div>
            </div>
            <div className="ml-4">
              <p className="text-sm font-medium text-gray-600">Total Quotes</p>
              <p className="text-2xl font-semibold text-gray-900">{stats.totalQuotes}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Recent Data */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Latest Movie Quote Trivia Scores */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-medium text-gray-900">Latest Movie Quote Trivia Scores</h3>
          </div>
          <div className="px-6 py-4">
            {recentData.triviaGames.length > 0 ? (
              <ul className="space-y-3">
                {recentData.triviaGames.map((game) => (
                  <li key={game.id} className="flex justify-between items-center">
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {game.created_by_name || 'Guest Player'}
                      </p>
                      <p className="text-sm text-gray-500">
                        {new Date(game.game_completed_at || game.created_at).toLocaleDateString()}
                      </p>
                    </div>
                    <span className="text-lg font-bold text-green-600">
                      {game.score} pts
                    </span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-gray-500 text-center py-4">No high scores yet</p>
            )}
          </div>
        </div>

        {/* Recent Movies */}
        <div className="bg-white shadow rounded-lg">
          <div className="px-6 py-4 border-b border-gray-200">
            <h3 className="text-lg font-medium text-gray-900">Recent Movies</h3>
          </div>
          <div className="px-6 py-4">
            {recentData.movies.length > 0 ? (
              <ul className="space-y-3">
                {recentData.movies.map((movie) => (
                  <li key={movie.id} className="flex justify-between items-center">
                    <div>
                      <p className="text-sm font-medium text-gray-900">{movie.name}</p>
                      <p className="text-sm text-gray-500">
                        {movie.release_year && `${movie.release_year} • `}
                        {movie.synopsis || 'No synopsis available'}
                      </p>
                    </div>
                    <span className="text-xs text-gray-400">ID: {movie.id}</span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-gray-500 text-center py-4">No movies found</p>
            )}
          </div>
        </div>
      </div>

      {/* Quick Actions */}
      <div className="bg-white shadow rounded-lg p-6">
        <h3 className="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <a
            href="/users"
            className="block p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition-all"
          >
            <h4 className="font-medium text-gray-900">Manage Users</h4>
            <p className="text-sm text-gray-600 mt-1">View and edit user accounts</p>
          </a>
          
          <a
            href="/movies"
            className="block p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition-all"
          >
            <h4 className="font-medium text-gray-900">Manage Movies</h4>
            <p className="text-sm text-gray-600 mt-1">Add and edit movie records</p>
          </a>
          
          <a
            href="/quotes"
            className="block p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:shadow-md transition-all"
          >
            <h4 className="font-medium text-gray-900">Manage Quotes</h4>
            <p className="text-sm text-gray-600 mt-1">View and edit movie quotes</p>
          </a>
          
          <a
            href="/trivia"
            className="block p-4 border border-green-200 rounded-lg hover:border-green-500 hover:shadow-md transition-all bg-green-50"
          >
            <h4 className="font-medium text-green-900">🎬 Movie Trivia</h4>
            <p className="text-sm text-green-700 mt-1">Test your movie knowledge!</p>
          </a>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;

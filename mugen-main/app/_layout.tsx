import { DarkTheme, DefaultTheme, ThemeProvider } from '@react-navigation/native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import 'react-native-reanimated';
import { ThemeProvider as MugenThemeProvider, useColors } from './context/ThemeContext';

export const unstable_settings = { anchor: '(tabs)' };

function InnerLayout() {
  const { isDark } = useColors();
  return (
    <ThemeProvider value={isDark ? DarkTheme : DefaultTheme}>
      <Stack>
        <Stack.Screen name="onboarding"                  options={{ headerShown: false }} />
        <Stack.Screen name="login"                       options={{ headerShown: false }} />
        <Stack.Screen name="register"                    options={{ headerShown: false }} />
        <Stack.Screen name="home"                        options={{ headerShown: false }} />
        <Stack.Screen name="personalizado"               options={{ headerShown: false }} />
        <Stack.Screen name="(tabs)"                      options={{ headerShown: false }} />
        <Stack.Screen name="screens/SettingsScreen"      options={{ headerShown: false, presentation: 'modal' }} />
        <Stack.Screen name="screens/EditProfileScreen"   options={{ headerShown: false, presentation: 'modal' }} />
        <Stack.Screen name="screens/SecurityScreen"      options={{ headerShown: false, presentation: 'modal' }} />
        <Stack.Screen name="screens/RoomDetailScreen"       options={{ headerShown: false }} />
        <Stack.Screen name="screens/LiveSessionScreen"      options={{ headerShown: false }} />
        <Stack.Screen name="screens/CreateChallengeScreen" options={{ headerShown: false }} />
        <Stack.Screen name="screens/JoinChallengeScreen"   options={{ headerShown: false }} />
        <Stack.Screen name="screens/ScanQRScreen"          options={{ headerShown: false, presentation: 'modal' }} />
      </Stack>
      <StatusBar style={isDark ? 'light' : 'dark'} />
    </ThemeProvider>
  );
}

export default function RootLayout() {
  return (
    <MugenThemeProvider>
      <InnerLayout />
    </MugenThemeProvider>
  );
}

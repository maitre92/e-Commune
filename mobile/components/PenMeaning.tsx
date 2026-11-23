import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useEffect, useRef, useState } from 'react';
import { Animated, Easing, Platform, StyleSheet, Text, View } from 'react-native';

/** Ce que le stylo écrit, phrase après phrase, sous « e-COMMUNE » (page de connexion). */
const PHRASES = [
  '« e » comme électronique',
  'e-Commune : la commune numérique',
  'Vignettes · État civil · Caisse',
];

const WRITE_MS = 70; // une lettre
const HOLD_MS = 2200; // phrase complète affichée
const ERASE_MS = 25; // effacement

/**
 * Un stylo écrit lettre à lettre la signification d'e-Commune (le « e » d'électronique), puis les trois services,
 * en boucle. Le stylo suit la dernière lettre et bouge comme une main qui écrit.
 */
export default function PenMeaning() {
  const [phrase, setPhrase] = useState(0);
  const [length, setLength] = useState(0);
  const [erasing, setErasing] = useState(false);
  const wiggle = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.sequence([
        Animated.timing(wiggle, { toValue: 1, duration: 140, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
        Animated.timing(wiggle, { toValue: 0, duration: 140, easing: Easing.inOut(Easing.quad), useNativeDriver: true }),
      ]),
    );
    loop.start();
    return () => loop.stop();
  }, [wiggle]);

  useEffect(() => {
    const text = PHRASES[phrase];
    let timer: ReturnType<typeof setTimeout>;

    if (!erasing && length < text.length) {
      timer = setTimeout(() => setLength(length + 1), WRITE_MS);
    } else if (!erasing) {
      timer = setTimeout(() => setErasing(true), HOLD_MS);
    } else if (length > 0) {
      timer = setTimeout(() => setLength(length - 1), ERASE_MS);
    } else {
      setErasing(false);
      setPhrase((phrase + 1) % PHRASES.length);
    }

    return () => clearTimeout(timer);
  }, [phrase, length, erasing]);

  const writing = !erasing && length < PHRASES[phrase].length;
  const rotate = wiggle.interpolate({ inputRange: [0, 1], outputRange: ['-8deg', '6deg'] });
  const translateY = wiggle.interpolate({ inputRange: [0, 1], outputRange: [0, -3] });

  return (
    <View style={styles.row} accessible accessibilityLabel="e-Commune : « e » comme électronique, la commune numérique">
      <Text style={styles.text}>{PHRASES[phrase].slice(0, length)}</Text>
      <Animated.View style={{ transform: writing ? [{ rotate }, { translateY }] : [{ rotate: '-8deg' }] }}>
        <MaterialCommunityIcons name="fountain-pen-tip" size={22} color="#fcd116" style={styles.pen} />
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', alignItems: 'flex-end', justifyContent: 'center', minHeight: 30, marginTop: 6 },
  text: {
    color: '#ffffff',
    fontSize: 17,
    fontStyle: 'italic',
    fontWeight: '600',
    fontFamily: Platform.select({ ios: 'Snell Roundhand', android: 'serif', default: undefined }),
    textShadowColor: 'rgba(0,0,0,0.35)',
    textShadowRadius: 6,
  },
  pen: { marginLeft: 2, marginBottom: 2 },
});
